<?php

namespace App\Http\Requests\Auth;

use App\Support\Regles\MotDePasse;
use Illuminate\Foundation\Http\FormRequest;

class ReinitialisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'mot_de_passe' => ['required', 'confirmed', MotDePasse::regle()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'courriel', 'code' => 'code', 'mot_de_passe' => 'mot de passe'];
    }
}
