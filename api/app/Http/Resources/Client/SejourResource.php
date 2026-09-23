<?php

namespace App\Http\Resources\Client;

use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Occupant;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\ConfirmationDeSejour;
use App\Domain\Sejours\Services\CycleDuSejour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce que le CLIENT voit de son séjour. Jamais le prix propriétaire figé, jamais un numéro de pièce.
 * L'adresse exacte et les consignes d'accès ne sont remises qu'après confirmation (CdC § 5.1 et § 5.3).
 *
 * @mixin Sejour
 */
class SejourResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $logement = $this->logement;
        $residence = $logement->residence;
        $confirme = in_array($this->etat, [EtatDuSejour::Confirme, EtatDuSejour::Arrive], true);

        return [
            'reference' => $this->reference,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'arrivee' => $this->arrivee->format('Y-m-d'),
            'depart' => $this->depart->format('Y-m-d'),
            'nombre_de_nuits' => $this->nombreDeNuits(),
            'adultes' => $this->adultes,
            'enfants' => $this->enfants,
            'logement' => [
                'reference' => $logement->reference, 'nom' => $logement->nom, 'resume' => $logement->resume(),
                'residence' => $residence->nom,
                'lieu' => ['commune' => $residence->quartier->commune->nom, 'quartier' => $residence->quartier->nom],
            ],
            // Le code d'arrivée : au client titulaire, et à lui seul, une fois le séjour confirmé (CdC § 5.3).
            'code_d_arrivee' => $this->etat === EtatDuSejour::Confirme ? app(CodesSecrets::class)->lirePourLeClient($this->resource, ConfirmationDeSejour::CODE_D_ARRIVEE) : null,
            // Remis après confirmation seulement.
            'acces' => $confirme ? ['adresse' => $residence->adresse, 'repere' => $residence->repere, 'consignes' => $residence->consignes_acces] : null,
            'mode_reglement' => $this->mode_reglement,
            'bon_de_commande' => $this->getAttribute('bon_de_commande'),
            'devis' => $this->devis,
            'net_a_payer' => $this->net_a_payer,
            'caution' => $this->caution,
            'acompte_exige' => $this->acompte_exige,
            'points_utilises' => $this->getAttribute('points_utilises'),
            'reduction_points' => $this->getAttribute('reduction_points'),
            'expire_le' => $this->expire_le?->format('d/m/Y H:i:s'),
            'annulation' => [
                'politique' => $this->politique_annulation->value,
                'politique_libelle' => $this->politique_annulation->libelle(),
                'gratuite_jusqu_au' => $this->arrivee->copy()->subDays($this->annulation_delai_jours)->format('d/m/Y'),
                'pourcentage_retenu_ensuite' => $this->annulation_pourcentage_retenu,
                'retenu_si_annule_maintenant' => app(CycleDuSejour::class)->montantRetenuSiAnnulation($this->resource),
                'annule_le' => $this->annule_le?->format('d/m/Y H:i:s'),
                'motif' => $this->motif_annulation,
                'montant_retenu' => $this->montant_retenu_annulation,
            ],
            'occupants' => $this->whenLoaded('occupants', fn () => $this->occupants->map(fn (Occupant $o): array => [
                'nom' => $o->nom, 'prenoms' => $o->prenoms, 'enfant' => $o->enfant, 'type_piece' => $o->type_piece,
                // Le numéro n'est jamais renvoyé : on dit seulement s'il a été fourni.
                'piece_fournie' => $o->numero_piece !== null,
            ])),
        ];
    }
}
