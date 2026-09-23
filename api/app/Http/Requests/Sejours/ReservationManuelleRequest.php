<?php

namespace App\Http\Requests\Sejours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Réservation manuelle par la réception (CdC § 6.1) : téléphone, walk-in, canal externe
 * (Booking, Airbnb, en attendant le channel manager du lot 4). Un client existant, ou
 * les coordonnées d'un nouveau.
 */
class ReservationManuelleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'canal' => ['required', Rule::in(['telephone', 'walk_in', 'canal_externe'])],
            'client_id' => ['nullable', 'integer', 'required_without:client', 'prohibits:client'],
            'client.nom' => ['required_without:client_id', 'string', 'max:100'],
            'client.prenoms' => ['nullable', 'string', 'max:150'],
            'client.email' => ['required_without:client_id', 'email:rfc', 'max:255'],
            'client.telephone' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/'],

            'reference_logement' => ['required', 'string', 'max:40'],
            'arrivee' => ['required', 'date', 'after_or_equal:today'],
            'depart' => ['required', 'date', 'after:arrivee'],
            'adultes' => ['required', 'integer', 'min:1', 'max:60'],
            'enfants' => ['nullable', 'integer', 'min:0', 'max:60'],
            'arrivee_tardive' => ['nullable', 'boolean'],
            'depart_tardif' => ['nullable', 'boolean'],
            'heure_arrivee_prevue' => ['nullable', 'date_format:H:i'],
            'mode_reglement' => ['required', Rule::in(['en_ligne', 'agence', 'a_terme'])],
            'bon_de_commande' => ['nullable', 'string', 'max:100'],
            'points_utilises' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'code_promo' => ['nullable', 'string', 'max:30'],
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
        return [
            'canal' => 'canal', 'client_id' => 'client', 'client.nom' => 'nom du client', 'client.email' => 'courriel du client',
            'client.telephone' => 'téléphone du client', 'reference_logement' => 'logement', 'arrivee' => 'date d’arrivée',
            'depart' => 'date de départ', 'adultes' => 'nombre d’adultes', 'enfants' => 'nombre d’enfants',
            'heure_arrivee_prevue' => 'heure d’arrivée prévue', 'mode_reglement' => 'mode de règlement',
            'bon_de_commande' => 'bon de commande', 'points_utilises' => 'points de fidélité', 'code_promo' => 'code promo',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'client_id.prohibits' => 'Choisissez un client existant OU indiquez les coordonnées d’un nouveau, pas les deux.',
            'arrivee.after_or_equal' => 'La date d’arrivée ne peut pas être passée.',
        ];
    }
}
