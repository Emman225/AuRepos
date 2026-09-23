<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ConnexionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Un seul champ : courriel (clients, partenaires), identifiant généré (personnel) ou téléphone.
            'identifiant' => ['required', 'string', 'max:255'],
            'mot_de_passe' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['identifiant' => 'identifiant', 'mot_de_passe' => 'mot de passe'];
    }
}
