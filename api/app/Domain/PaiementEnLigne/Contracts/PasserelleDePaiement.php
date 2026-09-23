<?php

namespace App\Domain\PaiementEnLigne\Contracts;

use App\Domain\PaiementEnLigne\Models\PaiementEnLigne;

/**
 * Ce que la plateforme attend d'une passerelle de paiement, quelle qu'elle soit.
 * PaySecure aujourd'hui ; une autre demain, sans toucher au reste du code.
 */
interface PasserelleDePaiement
{
    public function nom(): string;

    public function estConfiguree(): bool;

    /**
     * Ouvre une transaction chez la passerelle et rend l'adresse où envoyer le client.
     *
     * @return array{url: string, reference_passerelle: string|null, reponse: array<string, mixed>}
     */
    public function initier(PaiementEnLigne $paiement, string $urlDeRetour, string $urlDeRappel): array;

    /**
     * Interroge la passerelle sur l'état RÉEL d'une transaction (serveur à serveur).
     * C'est la seule source de vérité : le contenu d'un rappel ne se croit jamais sur parole.
     *
     * @return array{etat: string, montant: int|null, mode: string|null, reference_passerelle: string|null, reponse: array<string, mixed>}
     */
    public function verifier(PaiementEnLigne $paiement): array;

    /**
     * Le rappel est-il authentique ? Signature, secret partagé…
     *
     * @param  array<string, mixed>  $donnees
     */
    public function rappelAuthentique(array $donnees, ?string $signature): bool;

    /**
     * Référence de NOTRE paiement, retrouvée dans le rappel.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function referenceDuRappel(array $donnees): ?string;
}
