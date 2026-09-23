<?php

namespace App\Http\Requests\Auth;

use App\Support\Regles\MotDePasse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
            // « 07 07 07 07 07 » et « 0707070707 » sont le même numéro.
            'telephone' => is_string($this->telephone) ? (preg_replace('/[\s.\-]/', '', $this->telephone) ?: null) : null,
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'telephone' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('users', 'telephone')],
            'mot_de_passe' => ['required', 'confirmed', MotDePasse::regle()],
            'conditions_acceptees' => ['accepted'],
            // Code d'un apporteur d'affaires : facultatif, contrôlé par App\Domain\Partenaires\Services\Parrainage.
            'code_parrain' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nom' => 'nom', 'prenoms' => 'prénoms', 'email' => 'courriel', 'telephone' => 'téléphone',
            'mot_de_passe' => 'mot de passe', 'conditions_acceptees' => 'conditions générales',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Un compte existe déjà avec ce courriel. Connectez-vous ou réinitialisez votre mot de passe.',
            'telephone.unique' => 'Un compte existe déjà avec ce numéro de téléphone.',
            'telephone.regex' => 'Le numéro de téléphone doit comporter 8 à 15 chiffres.',
            'conditions_acceptees.accepted' => 'Vous devez accepter les conditions générales.',
        ];
    }
}
