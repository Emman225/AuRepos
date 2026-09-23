<?php

namespace App\Http\Resources\Proprietaire;

use App\Domain\Sejours\Models\Sejour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue PROPRIÉTAIRE d'un séjour (lecture seule, CdC § 7) : jamais le code d'arrivée, jamais
 * les coordonnées du client (téléphone, courriel) — même règle de confidentialité que le
 * back office (CdC § 6.3) ; le nom suffit à identifier le séjour.
 *
 * @mixin Sejour
 */
class SejourResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'arrivee' => $this->arrivee->format('Y-m-d'),
            'depart' => $this->depart->format('Y-m-d'),
            'nombre_de_nuits' => $this->nombreDeNuits(),
            'client' => $this->client?->nomComplet(),
        ];
    }
}
