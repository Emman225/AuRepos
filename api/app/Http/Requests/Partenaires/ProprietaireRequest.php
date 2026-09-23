<?php

namespace App\Http\Requests\Partenaires;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Partenaires\Enums\ModeDeRemuneration;
use App\Domain\Partenaires\Enums\NatureJuridique;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProprietaireRequest extends FormRequest
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
        $proprietaire = $this->route('proprietaire');
        $userId = $proprietaire instanceof Proprietaire ? $proprietaire->user_id : null;

        return [
            // Le compte de connexion
            'nom' => [$requis, 'string', 'max:100'],
            'prenoms' => ['nullable', 'string', 'max:150'],
            'email' => [$requis, 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'telephone' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('users', 'telephone')->ignore($userId)],

            // La fiche
            'nature' => [$requis, Rule::enum(NatureJuridique::class)],
            'raison_sociale' => ['nullable', 'string', 'max:255', 'required_if:nature,'.NatureJuridique::Entreprise->value],
            'regime_fiscal' => ['sometimes', Rule::enum(RegimeFiscal::class)],
            'assujetti_tva' => ['sometimes', 'boolean'],
            'ncc' => ['nullable', 'string', 'max:30', 'required_if_accepted:assujetti_tva'],
            'rccm' => ['nullable', 'string', 'max:60'],
            'adresse' => ['nullable', 'string', 'max:255'],

            // Le mandat
            'mode_remuneration' => ['sometimes', Rule::enum(ModeDeRemuneration::class)],
            'taux_commission' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:mode_remuneration,'.ModeDeRemuneration::Commission->value],
            'part_entreprise_cautions' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mandat_signe_le' => ['nullable', 'date', 'before_or_equal:today'],
            'mandat_expire_le' => ['nullable', 'date', 'after:mandat_signe_le'],
            'bons_valides_automatiquement' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:3000'],

            // Le compte interne de l'entreprise : administrateurs seulement (contrôlé dans le contrôleur).
            'interne' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nom' => 'nom', 'prenoms' => 'prénoms', 'email' => 'courriel', 'telephone' => 'téléphone',
            'nature' => 'nature juridique', 'raison_sociale' => 'raison sociale', 'regime_fiscal' => 'régime fiscal',
            'assujetti_tva' => 'assujettissement à la TVA', 'ncc' => 'NCC', 'rccm' => 'RCCM',
            'mode_remuneration' => 'mode de rémunération', 'taux_commission' => 'taux de commission',
            'part_entreprise_cautions' => 'part de l’entreprise sur les cautions', 'mandat_signe_le' => 'date de signature du mandat',
            'mandat_expire_le' => 'date d’expiration du mandat',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'raison_sociale.required_if' => 'La raison sociale est obligatoire pour une entreprise.',
            'ncc.required_if_accepted' => 'Le NCC est obligatoire pour un propriétaire assujetti à la TVA.',
            'taux_commission.required_if' => 'Le taux de commission est obligatoire en mode « commission ».',
            'telephone.regex' => 'Le numéro de téléphone doit comporter 8 à 15 chiffres.',
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
