<?php

namespace App\Http\Requests\Catalogue;

use App\Domain\Catalogue\Enums\ModeDeVente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // le profil est contrôlé par le groupe de routes
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        // En modification (PUT), seuls les champs envoyés sont exigés.
        $requis = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'proprietaire_id' => [$requis, 'integer', Rule::exists('proprietaires', 'id')],
            'quartier_id' => [$requis, 'integer', Rule::exists('quartiers', 'id')->where('actif', true)],
            'nom' => [$requis, 'string', 'min:3', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'repere' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'description' => ['nullable', 'string', 'max:5000'],
            'consignes_acces' => ['nullable', 'string', 'max:3000'],
            'mode_vente' => ['sometimes', Rule::enum(ModeDeVente::class)],
            'active' => ['sometimes', 'boolean'],
            'mise_en_avant' => ['sometimes', 'boolean'],
            'equipements' => ['sometimes', 'array'],
            // Une résidence ne porte que des équipements COMMUNS (piscine, parking, gardiennage…).
            'equipements.*' => ['integer', Rule::exists('equipements', 'id')->where('portee', 'residence')->where('actif', true)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'proprietaire_id' => 'propriétaire', 'quartier_id' => 'quartier', 'nom' => 'nom', 'adresse' => 'adresse',
            'repere' => 'repère', 'consignes_acces' => 'consignes d’accès', 'mode_vente' => 'mode de vente',
            'equipements' => 'équipements', 'equipements.*' => 'équipement',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['equipements.*.exists' => 'Cet équipement n’est pas un équipement commun de résidence.'];
    }
}
