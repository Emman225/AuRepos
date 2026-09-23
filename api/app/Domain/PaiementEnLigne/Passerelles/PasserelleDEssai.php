<?php

namespace App\Domain\PaiementEnLigne\Passerelles;

use App\Domain\PaiementEnLigne\Contracts\PasserelleDePaiement;
use App\Domain\PaiementEnLigne\Models\PaiementEnLigne;
use Illuminate\Support\Facades\Cache;

/**
 * Passerelle d'ESSAI : elle ne contacte aucun service. Elle sert au développement local et aux
 * tests, où l'on décide à la main du sort de chaque transaction.
 *
 * Elle se comporte comme une vraie passerelle sur les points qui comptent : le rappel doit être
 * signé, l'état réel ne se lit que par une vérification serveur à serveur, et le montant qu'elle
 * annonce peut différer de celui attendu — c'est ainsi qu'on éprouve les contrôles.
 */
final class PasserelleDEssai implements PasserelleDePaiement
{
    public const SECRET = 'secret-d-essai';

    public function nom(): string
    {
        return 'essai';
    }

    public function estConfiguree(): bool
    {
        return true;
    }

    public function initier(PaiementEnLigne $paiement, string $urlDeRetour, string $urlDeRappel): array
    {
        return [
            'url' => $urlDeRetour.'?essai='.$paiement->reference,
            'reference_passerelle' => 'ESSAI-'.$paiement->reference,
            'reponse' => ['essai' => true],
        ];
    }

    public function verifier(PaiementEnLigne $paiement): array
    {
        /** @var array{etat: string, montant?: int|null, mode?: string|null} $decide */
        $decide = Cache::get('paiement_essai.'.$paiement->reference, ['etat' => 'en_attente']);

        return [
            'etat' => $decide['etat'],
            'montant' => $decide['montant'] ?? $paiement->montant,
            'mode' => $decide['mode'] ?? 'mobile_money',
            'reference_passerelle' => 'ESSAI-'.$paiement->reference,
            'reponse' => $decide,
        ];
    }

    public function rappelAuthentique(array $donnees, ?string $signature): bool
    {
        if ($signature === null) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', (string) json_encode($donnees, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), self::SECRET), $signature);
    }

    /** @param array<string, mixed> $donnees */
    public function referenceDuRappel(array $donnees): ?string
    {
        $reference = $donnees['reference'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /** Décide du sort d'une transaction, comme le ferait le client sur la page de la passerelle. */
    public static function decider(string $reference, string $etat, ?int $montant = null, ?string $mode = null): void
    {
        Cache::put('paiement_essai.'.$reference, array_filter(['etat' => $etat, 'montant' => $montant, 'mode' => $mode], fn ($v) => $v !== null), now()->addHour());
    }

    /** @param array<string, mixed> $donnees */
    public static function signer(array $donnees): string
    {
        return hash_hmac('sha256', (string) json_encode($donnees, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), self::SECRET);
    }
}
