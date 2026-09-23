<?php

namespace App\Http\Requests\Repas;

use App\Domain\Repas\Enums\CategorieProduit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProduitRequest extends FormRequest
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
            'nom' => [$requis, 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'categorie' => [$requis, Rule::enum(CategorieProduit::class)],
            'prix_restaurateur' => [$requis, 'integer', 'min:0'],
            'disponible' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nom' => 'nom', 'description' => 'description', 'categorie' => 'catégorie',
            'prix_restaurateur' => 'prix restaurateur', 'disponible' => 'disponible',
        ];
    }
}
