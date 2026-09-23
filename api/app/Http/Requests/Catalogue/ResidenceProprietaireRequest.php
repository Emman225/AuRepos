<?php

namespace App\Http\Requests\Catalogue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / modification d'une résidence par son PROPRIÉTAIRE (CdC § 7.1 : « le propriétaire
 * crée lui-même sa résidence... depuis son compte »). Contrairement à App\Http\Requests\Catalogue\ResidenceRequest
 * (back office), il n'y a ni `proprietaire_id` (c'est TOUJOURS le sien — posé par le contrôleur),
 * ni `active`, ni `mise_en_avant` : des décisions qui restent à l'administration.
 */
class ResidenceProprietaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // le profil est contrôlé par le groupe de routes
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $requis = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'quartier_id' => [$requis, 'integer', Rule::exists('quartiers', 'id')->where('actif', true)],
            'nom' => [$requis, 'string', 'min:3', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'repere' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'description' => ['nullable', 'string', 'max:5000'],
            'consignes_acces' => ['nullable', 'string', 'max:3000'],
            'equipements' => ['sometimes', 'array'],
            'equipements.*' => ['integer', Rule::exists('equipements', 'id')->where('portee', 'residence')->where('actif', true)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'quartier_id' => 'quartier', 'nom' => 'nom', 'adresse' => 'adresse', 'repere' => 'repère',
            'consignes_acces' => 'consignes d’accès', 'equipements' => 'équipements', 'equipements.*' => 'équipement',
        ];
    }
}
