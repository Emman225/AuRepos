<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Comptes\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Écrit le journal d'audit.
 *
 * Deux règles reprises de Mon Gravier :
 *   - l'audit ne fait JAMAIS échouer l'opération qu'il trace : une panne du
 *     journal part dans les logs, l'encaissement ou la réservation aboutit ;
 *   - les champs sensibles n'y entrent jamais, même hachés.
 */
final class JournalAudit
{
    /** Champs qui ne doivent apparaître dans aucune trace. */
    public const CHAMPS_SENSIBLES = [
        'password', 'mot_de_passe', 'remember_token', 'code_hash', 'code', 'jeton', 'token',
    ];

    /** Champs techniques sans intérêt pour la lecture du journal. */
    private const CHAMPS_IGNORES = ['created_at', 'updated_at', 'deleted_at', 'derniere_connexion_le'];

    /**
     * @param  array<string, mixed>|null  $avant
     * @param  array<string, mixed>|null  $apres
     */
    public function consigner(string $action, string $recit, ?Model $sujet = null, ?array $avant = null, ?array $apres = null, ?User $auteur = null): void
    {
        try {
            $auteur ??= $this->auteurCourant();
            $requete = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            // Transaction imbriquée = point de sauvegarde : si l'écriture du journal
            // échoue, PostgreSQL n'annule que cette ligne, pas l'opération en cours.
            DB::transaction(fn () => EntreeAudit::create([
                'user_id' => $auteur?->id,
                'auteur' => $auteur?->nomComplet() ?? 'Système',
                'profil' => $auteur?->profil->value,
                'action' => $action,
                'sujet_type' => $sujet ? class_basename($sujet) : null,
                'sujet_id' => $sujet?->getKey(),
                'sujet_libelle' => $sujet ? self::libelleDe($sujet) : null,
                'avant' => $avant === null ? null : self::nettoyer($avant),
                'apres' => $apres === null ? null : self::nettoyer($apres),
                'recit' => $recit,
                'ip' => $requete?->ip(),
                'cree_le' => now(),
                'adresse' => $requete ? mb_substr($requete->method().' '.$requete->path(), 0, 255) : null,
            ]));
        } catch (Throwable $e) {
            Log::error('Écriture du journal d’audit impossible', ['action' => $action, 'erreur' => $e->getMessage()]);
        }
    }

    public static function libelleDe(Model $sujet): string
    {
        return method_exists($sujet, 'libelleAudit')
            ? (string) $sujet->libelleAudit()
            : class_basename($sujet).' n° '.$sujet->getKey();
    }

    /**
     * @param  array<string, mixed>  $valeurs
     * @return array<string, mixed>
     */
    public static function nettoyer(array $valeurs): array
    {
        $propre = [];
        foreach ($valeurs as $champ => $valeur) {
            if (in_array($champ, self::CHAMPS_IGNORES, true)) {
                continue;
            }
            $propre[$champ] = in_array($champ, self::CHAMPS_SENSIBLES, true) ? '••• (masqué)' : self::lisible($valeur);
        }

        return $propre;
    }

    private static function lisible(mixed $valeur): mixed
    {
        return match (true) {
            $valeur instanceof BackedEnum => $valeur->value,
            $valeur instanceof DateTimeInterface => $valeur->format('d/m/Y H:i:s'),
            default => $valeur,
        };
    }

    private function auteurCourant(): ?User
    {
        $utilisateur = auth('api')->user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }
}
