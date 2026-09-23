<?php

namespace App\Http\Requests\Sejours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Une réservation n'envoie que des INTENTIONS : aucun prix, aucune taxe, aucune remise. */
class ReservationRequest extends FormRequest
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
            'mode_reglement' => ['required', Rule::in(['en_ligne', 'agence', 'a_terme'])],
            'bon_de_commande' => ['nullable', 'string', 'max:100'],
            // Points demandés : le serveur les ramène à ce qui est réellement utilisable.
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
            'reference_logement' => 'logement', 'arrivee' => 'date d’arrivée', 'depart' => 'date de départ', 'adultes' => 'nombre d’adultes',
            'enfants' => 'nombre d’enfants', 'heure_arrivee_prevue' => 'heure d’arrivée prévue', 'mode_reglement' => 'mode de règlement',
            'bon_de_commande' => 'bon de commande', 'points_utilises' => 'points de fidélité', 'occupants.*.nom' => 'nom de l’occupant', 'occupants.*.type_piece' => 'type de pièce',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['arrivee.after_or_equal' => 'La date d’arrivée ne peut pas être passée.'];
    }
}
