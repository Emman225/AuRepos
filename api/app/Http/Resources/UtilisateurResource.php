<?php

namespace App\Http\Resources;

use App\Domain\Comptes\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce que le web et le mobile savent d'un compte. Jamais le modèle brut :
 * Mon Gravier renvoyait ses modèles Eloquent tels quels, colonnes internes comprises.
 *
 * @mixin User
 */
class UtilisateurResource extends JsonResource
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
            'profil' => $this->profil->value,
            'profil_libelle' => $this->profil->libelle(),
            // Sert à la redirection après connexion ; le serveur, lui, cloisonne par profil.
            'espace' => $this->profil->espace(),
            'agence' => $this->agence ? ['id' => $this->agence->id, 'nom' => $this->agence->nom] : null,
            'peut_encaisser' => $this->peutEncaisser(),
        ];
    }
}
