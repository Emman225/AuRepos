<?php

namespace App\Http\Requests\Sejours;

use Illuminate\Foundation\Http\FormRequest;

/** Un devis n'envoie que des INTENTIONS, comme une réservation — jamais un prix (CdC § 5.1). */
class DevisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reference_logement' => ['required', 'string', 'max:40'],
            'arrivee' => ['required', 'date', 'after_or_equal:today'],
            'depart' => ['required', 'date', 'after:arrivee'],
            'adultes' => ['required', 'integer', 'min:1', 'max:60'],
            'enfants' => ['nullable', 'integer', 'min:0', 'max:60'],
            'arrivee_tardive' => ['nullable', 'boolean'],
            'depart_tardif' => ['nullable', 'boolean'],
            'heure_arrivee_prevue' => ['nullable', 'date_format:H:i'],
            'points_utilises' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'code_promo' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'reference_logement' => 'logement', 'arrivee' => 'date d’arrivée', 'depart' => 'date de départ',
            'adultes' => 'nombre d’adultes', 'enfants' => 'nombre d’enfants', 'heure_arrivee_prevue' => 'heure d’arrivée prévue',
            'points_utilises' => 'points de fidélité', 'code_promo' => 'code promo',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['arrivee.after_or_equal' => 'La date d’arrivée ne peut pas être passée.'];
    }
}
