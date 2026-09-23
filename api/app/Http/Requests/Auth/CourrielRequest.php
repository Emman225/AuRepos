<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Demande portant sur une seule adresse : renvoi du code, mot de passe oublié. */
class CourrielRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'courriel'];
    }
}
