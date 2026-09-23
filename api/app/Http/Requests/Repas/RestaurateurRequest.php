<?php

namespace App\Http\Requests\Repas;

use App\Domain\Repas\Models\Restaurateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création ou modification d'une fiche restaurateur. `pourcentage_plateforme` n'y figure
 * PAS : il ne se change que par double validation (voir RestaurateursController::
 * proposerLePourcentage), jamais par ce formulaire ordinaire.
 */
class RestaurateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // le profil est contrôlé par le groupe de routes
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : null,
            'telephone' => is_string($this->telephone) ? (preg_replace('/[\s.\-]/', '', $this->telephone) ?: null) : null,
        ], fn ($v) => $v !== null));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $creation = $this->isMethod('POST');
        $requis = $creation ? 'required' : 'sometimes';
        $restaurateur = $this->route('restaurateur');
        $userId = $restaurateur instanceof Restaurateur ? $restaurateur->user_id : null;

        return [
            'nom' => [$requis, 'string', 'max:100'],
            'prenoms' => ['nullable', 'string', 'max:150'],
            'email' => [$requis, 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'telephone' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('users', 'telephone')->ignore($userId)],

            'assujetti_tva' => ['sometimes', 'boolean'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nom' => 'nom', 'prenoms' => 'prénoms', 'email' => 'courriel', 'telephone' => 'téléphone',
            'assujetti_tva' => 'assujettissement à la TVA', 'actif' => 'actif',
        ];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} [compte, fiche] */
    public function compteEtFiche(): array
    {
        $valide = $this->validated();
        $compte = array_intersect_key($valide, array_flip(['nom', 'prenoms', 'email', 'telephone']));

        return [$compte, array_diff_key($valide, $compte)];
    }
}
