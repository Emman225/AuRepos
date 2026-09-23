<?php

namespace App\Http\Requests\Sejours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** « Transformer en réservation d'un clic » (CdC § 5.1) : seul ce qui manquait au devis. */
class TransformationDevisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'mode_reglement' => ['required', Rule::in(['en_ligne', 'agence', 'a_terme'])],
            'bon_de_commande' => ['nullable', 'string', 'max:100'],
            'occupants' => ['nullable', 'array', 'max:60'],
            'occupants.*.nom' => ['required', 'string', 'max:100'],
            'occupants.*.prenoms' => ['nullable', 'string', 'max:150'],
            'occupants.*.enfant' => ['nullable', 'boolean'],
            'occupants.*.type_piece' => ['nullable', Rule::in(['cni', 'passeport', 'permis', 'carte_consulaire', 'autre'])],
            'occupants.*.numero_piece' => ['nullable', 'string', 'max:60'],
            'occupants.*.telephone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['mode_reglement' => 'mode de règlement', 'bon_de_commande' => 'bon de commande'];
    }
}
