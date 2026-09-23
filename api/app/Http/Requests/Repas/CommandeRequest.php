<?php

namespace App\Http\Requests\Repas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Le client passe commande auprès d'un restaurateur, pendant son séjour. */
class CommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'restaurateur_id' => ['required', 'integer', 'exists:restaurateurs,id'],
            'mode_reglement' => ['required', Rule::in(['en_ligne', 'note_du_sejour', 'a_terme'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'lignes' => ['required', 'array', 'min:1', 'max:50'],
            'lignes.*.produit_id' => ['required', 'integer', 'exists:produits,id'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'restaurateur_id' => 'restaurateur', 'mode_reglement' => 'mode de règlement', 'notes' => 'notes',
            'lignes' => 'lignes de commande', 'lignes.*.produit_id' => 'produit', 'lignes.*.quantite' => 'quantité',
        ];
    }
}
