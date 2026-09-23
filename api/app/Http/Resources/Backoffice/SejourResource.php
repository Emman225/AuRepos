<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\ConfirmationDeSejour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue BACK OFFICE d'un séjour. Elle ne contient JAMAIS le code d'arrivée : le personnel sait
 * qu'un code existe et peut le renvoyer au client, il ne peut pas le lire (CdC § 6.3).
 *
 * @mixin Sejour
 */
class SejourResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $residence = $this->logement->residence;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'canal' => $this->canal,
            'client' => $this->client ? ['id' => $this->client->id, 'nom' => $this->client->nomComplet(), 'email' => $this->client->email, 'telephone' => $this->client->telephone] : null,
            'logement' => ['id' => $this->logement->id, 'reference' => $this->logement->reference, 'nom' => $this->logement->nom, 'residence' => $residence->nom, 'residence_id' => $residence->id],
            'arrivee' => $this->arrivee->format('Y-m-d'),
            'depart' => $this->depart->format('Y-m-d'),
            'nombre_de_nuits' => $this->nombreDeNuits(),
            'adultes' => $this->adultes,
            'enfants' => $this->enfants,
            'mode_reglement' => $this->mode_reglement,
            'bon_de_commande' => $this->getAttribute('bon_de_commande'),
            'net_a_payer' => $this->net_a_payer,
            'caution' => $this->caution,
            'acompte_exige' => $this->acompte_exige,
            'reglement' => app(SoldeDesSejours::class)->de($this->resource),
            'expire_le' => $this->expire_le?->format('d/m/Y H:i:s'),
            'confirme_le' => $this->confirme_le?->format('d/m/Y H:i:s'),
            'agent_accueil_id' => $this->getAttribute('agent_accueil_id'),
            // Check-in / check-out (P2-SEJ-01, 04) et no-show (P2-SEJ-05) : visibles au back office (P2-BO-02).
            'arrive_le' => $this->getAttribute('arrive_le')?->format('d/m/Y H:i:s'),
            'parti_le' => $this->getAttribute('parti_le')?->format('d/m/Y H:i:s'),
            'no_show_le' => $this->getAttribute('no_show_le')?->format('d/m/Y H:i:s'),
            'caution_retenue' => $this->getAttribute('caution_retenue'),
            'caution_retenue_motif' => $this->getAttribute('caution_retenue_motif'),
            // Ce qui empêche encore de confirmer : affiché en clair sur « Réservations en attente ».
            'obstacles_a_la_confirmation' => app(ConfirmationDeSejour::class)->obstacles($this->resource),
            // Un indicateur, jamais le code lui-même.
            'code_d_arrivee_emis' => app(CodesSecrets::class)->existe($this->resource, ConfirmationDeSejour::CODE_D_ARRIVEE),
            'devis' => $this->devis,
        ];
    }
}
