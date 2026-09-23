<?php

namespace App\Domain\Codes\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Codes\Models\CodeSecret;
use App\Domain\Comptes\Models\User;
use App\Support\Api\ErreurMetier;
use Illuminate\Database\Eloquent\Model;

/**
 * Codes que le CLIENT détient et que l'agent SAISIT : code d'arrivée (check-in), code de prise
 * en charge (transfert), code de livraison (repas). « Les codes sont chez le client et jamais
 * chez l'agent » (CdC § 11).
 *
 * Trois façons seulement de toucher à un code :
 *   - generer()            → rend le code en clair UNE fois, à celui qui va l'envoyer au client ;
 *   - lirePourLeClient()   → pour l'espace du client, et lui seul ;
 *   - verifier()           → l'agent propose un code, le serveur répond oui ou non, jamais le code.
 */
final class CodesSecrets
{
    public const ESSAIS_MAXIMUM = 5;

    public function __construct(private readonly JournalAudit $journal) {}

    /** Crée le code, ou le REMPLACE (l'ancien ne vaut plus rien) : remet essais et verrou à zéro. */
    public function generer(Model $sujet, string $usage): string
    {
        $code = (string) random_int(100000, 999999);

        CodeSecret::updateOrCreate(
            ['sujet_type' => $sujet->getMorphClass(), 'sujet_id' => $sujet->getKey(), 'usage' => $usage],
            ['code' => $code, 'tentatives' => 0, 'verrouille_le' => null, 'utilise_le' => null, 'utilise_par' => null],
        );

        return $code;
    }

    /** À n'appeler QUE pour construire une réponse destinée au client titulaire. */
    public function lirePourLeClient(Model $sujet, string $usage): ?string
    {
        return $this->trouver($sujet, $usage)?->code;
    }

    public function existe(Model $sujet, string $usage): bool
    {
        return $this->trouver($sujet, $usage) !== null;
    }

    /**
     * L'agent saisit le code que le client lui remet. Cinq essais, puis le code se verrouille :
     * six chiffres se devinent en un million d'essais, pas en cinq.
     *
     * @throws ErreurMetier `code_incorrect`, `code_verrouille`, `code_deja_utilise`, `code_absent`
     */
    public function verifier(Model $sujet, string $usage, string $saisie, User $agent): void
    {
        $code = $this->trouver($sujet, $usage) ?? throw new ErreurMetier('Aucun code n’a été émis pour cette affaire.', 'code_absent', 422);

        if ($code->utilise_le !== null) {
            throw new ErreurMetier('Ce code a déjà servi.', 'code_deja_utilise');
        }
        if ($code->verrouille_le !== null) {
            throw new ErreurMetier('Ce code est verrouillé après trop d’essais. Un administrateur doit en émettre un nouveau pour le client.', 'code_verrouille', 423);
        }

        // hash_equals : comparaison en temps constant, pour ne rien laisser deviner par le chronomètre.
        if (! hash_equals($code->code, trim($saisie))) {
            $code->increment('tentatives');
            $restants = self::ESSAIS_MAXIMUM - $code->tentatives;

            if ($restants <= 0) {
                $code->update(['verrouille_le' => now()]);
                $this->journal->consigner('code_verrouille', "Code « {$usage} » verrouillé après ".self::ESSAIS_MAXIMUM.' essais : '.JournalAudit::libelleDe($sujet).'.', $sujet, auteur: $agent);
                throw new ErreurMetier('Code incorrect. Le code est maintenant verrouillé : un administrateur doit en émettre un nouveau.', 'code_verrouille', 423);
            }

            throw new ErreurMetier("Code incorrect. Il reste {$restants} essai(s).", 'code_incorrect', 422);
        }

        $code->update(['utilise_le' => now(), 'utilise_par' => $agent->id]);
    }

    public function noterLEnvoi(Model $sujet, string $usage): void
    {
        $this->trouver($sujet, $usage)?->update(['dernier_envoi_le' => now()]);
    }

    private function trouver(Model $sujet, string $usage): ?CodeSecret
    {
        return CodeSecret::query()
            ->where('sujet_type', $sujet->getMorphClass())->where('sujet_id', $sujet->getKey())->where('usage', $usage)
            ->first();
    }
}
