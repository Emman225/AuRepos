<?php

namespace App\Http\Requests\Catalogue;

use App\Domain\Catalogue\Enums\PolitiqueAnnulation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / modification d'un logement par son PROPRIÉTAIRE (CdC § 7.1). Mêmes champs
 * descriptifs que App\Http\Requests\Catalogue\LogementRequest (back office), sans `mise_en_avant`
 * (décision éditoriale de l'administration) ni prix ou état de publication (leurs propres circuits).
 */
class LogementProprietaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // le profil est contrôlé par le groupe de routes
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $requis = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'type_logement_id' => [$requis, 'integer', Rule::exists('types_logement', 'id')->where('actif', true)],
            'nom' => [$requis, 'string', 'min:2', 'max:150'],
            'nombre_pieces' => [$requis, 'integer', 'min:1', 'max:30'],
            'nombre_chambres' => ['sometimes', 'integer', 'min:0', 'max:30', 'lte:nombre_pieces'],
            'nombre_lits' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'nombre_salles_de_bain' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'capacite_de_base' => [$requis, 'integer', 'min:1', 'max:60'],
            'capacite_maximale' => [$requis, 'integer', 'min:1', 'max:60', 'gte:capacite_de_base'],
            'surface_m2' => ['nullable', 'integer', 'min:5', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'fumeur_autorise' => ['sometimes', 'boolean'],
            'animaux_autorises' => ['sometimes', 'boolean'],
            'fetes_autorisees' => ['sometimes', 'boolean'],
            'regles_maison' => ['nullable', 'string', 'max:5000'],
            'heure_arrivee' => ['nullable', 'date_format:H:i'],
            'heure_depart' => ['nullable', 'date_format:H:i'],
            'caution' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'duree_minimale' => ['nullable', 'integer', 'min:1', 'max:365'],
            'duree_maximale' => ['nullable', 'integer', 'min:1', 'max:730', 'gte:duree_minimale'],
            'politique_annulation' => ['sometimes', Rule::enum(PolitiqueAnnulation::class)],
            'equipements' => ['sometimes', 'array'],
            'equipements.*' => ['integer', Rule::exists('equipements', 'id')->where('portee', 'logement')->where('actif', true)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type_logement_id' => 'type de logement', 'nom' => 'nom', 'nombre_pieces' => 'nombre de pièces',
            'nombre_chambres' => 'nombre de chambres', 'nombre_lits' => 'nombre de lits',
            'nombre_salles_de_bain' => 'nombre de salles de bain', 'capacite_de_base' => 'capacité de base',
            'capacite_maximale' => 'capacité maximale', 'surface_m2' => 'surface', 'caution' => 'caution',
            'duree_minimale' => 'durée minimale', 'duree_maximale' => 'durée maximale',
            'heure_arrivee' => 'heure d’arrivée', 'heure_depart' => 'heure de départ',
            'politique_annulation' => 'politique d’annulation', 'equipements.*' => 'équipement',
        ];
    }
}
