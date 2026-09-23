<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class PersonnelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'nom_complet' => $this->nomComplet(),
            'email' => $this->email,
            'telephone' => $this->telephone,
            'identifiant' => $this->identifiant,
            'profil' => $this->profil->value,
            'profil_libelle' => $this->profil->libelle(),
            'statut' => $this->statut->value,
            'agence' => $this->agence ? ['id' => $this->agence->id, 'nom' => $this->agence->nom] : null,
            'residences' => $this->whenLoaded('residences', fn () => $this->residences->map(fn (Residence $r): array => ['id' => $r->id, 'nom' => $r->nom])),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
