<?php

namespace App\Domain\Notifications\Passerelles;

use App\Domain\Notifications\Contracts\PasserelleDeMessage;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Passerelle d'ESSAI, commune au SMS et au WhatsApp : elle ne contacte aucun service, elle
 * journalise seulement. Sert au développement local et aux tests, où l'on décide à la main
 * de l'échec d'un envoi (`PasserelleEssai::simulerUnEchecPour()`), comme pour le paiement.
 */
final class PasserelleEssai implements PasserelleDeMessage
{
    public function __construct(private readonly string $nom) {}

    public function nom(): string
    {
        return $this->nom;
    }

    public function estConfiguree(): bool
    {
        return true;
    }

    public function envoyer(string $destinataire, string $corps): void
    {
        $motif = Cache::pull('notification_essai_echec.'.$this->nom.'.'.$destinataire);
        if ($motif !== null) {
            throw new ErreurMetier((string) $motif, 'envoi_notification_echoue', 502);
        }

        Log::info("[essai {$this->nom}] message à {$destinataire}", ['corps' => $corps]);
    }

    /** Pour les tests : le PROCHAIN envoi vers ce destinataire échouera avec ce motif. */
    public static function simulerUnEchecPour(string $nom, string $destinataire, string $motif): void
    {
        Cache::put('notification_essai_echec.'.$nom.'.'.$destinataire, $motif, now()->addMinutes(5));
    }
}
