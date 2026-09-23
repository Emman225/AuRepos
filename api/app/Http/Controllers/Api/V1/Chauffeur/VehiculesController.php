<?php

namespace App\Http\Controllers\Api\V1\Chauffeur;

use App\Http\Controllers\Api\V1\Chauffeur\Concerns\ResoutLeChauffeurConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transferts\VehiculeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Espace chauffeur › mes véhicules (CdC § 6.6, Paramètres) : SES véhicules, en lecture/écriture. */
final class VehiculesController extends Controller
{
    use ResoutLeChauffeurConnecte;

    public function index(Request $request): JsonResponse
    {
        $chauffeur = $this->monChauffeur($request);

        return ReponseApi::succes(VehiculeResource::collection(
            $chauffeur->vehicules()->with('type')->orderBy('id')->get(),
        ));
    }

    public function creer(Request $request): JsonResponse
    {
        $chauffeur = $this->monChauffeur($request);

        $saisie = $request->validate([
            'type_vehicule_id' => ['required', 'integer', Rule::exists('types_vehicule', 'id')],
            'immatriculation' => ['required', 'string', 'max:20', Rule::unique('vehicules', 'immatriculation')],
        ], [], ['type_vehicule_id' => 'type de véhicule', 'immatriculation' => 'immatriculation']);

        $vehicule = $chauffeur->vehicules()->create($saisie);

        return ReponseApi::cree(new VehiculeResource($vehicule->load('type')), 'Véhicule ajouté.');
    }
}
