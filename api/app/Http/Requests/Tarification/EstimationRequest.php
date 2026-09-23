<?php

namespace App\Http\Requests\Tarification;

use Illuminate\Foundation\Http\FormRequest;

/** Ce qu'un client peut demander : des dates, des occupants, des options. Jamais un prix. */
class EstimationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'arrivee' => ['required', 'date', 'after_or_equal:today'],
            'depart' => ['required', 'date', 'after:arrivee'],
            'adultes' => ['required', 'integer', 'min:1', 'max:60'],
            'enfants' => ['nullable', 'integer', 'min:0', 'max:60'],
            'arrivee_tardive' => ['nullable', 'boolean'],
            'depart_tardif' => ['nullable', 'boolean'],
            'code_promo' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['arrivee' => 'date d’arrivée', 'depart' => 'date de départ', 'adultes' => 'nombre d’adultes', 'enfants' => 'nombre d’enfants'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['arrivee.after_or_equal' => 'La date d’arrivée ne peut pas être passée.'];
    }
}
