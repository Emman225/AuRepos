<?php

namespace App\Http\Requests\Repas;

use Illuminate\Foundation\Http\FormRequest;

class BaremeLivraisonRepasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // le profil est contrôlé par le groupe de routes
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $creation = $this->isMethod('POST');

        return [
            'residence_id' => [$creation ? 'required' : 'sometimes', 'integer', 'exists:residences,id'],
            'forfait' => [$creation ? 'required' : 'sometimes', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['residence_id' => 'résidence', 'forfait' => 'forfait'];
    }
}
