<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Transferts\Models\Chauffeur;
use App\Domain\Transferts\Models\Vehicule;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transferts\VehiculeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Véhicules d'un chauffeur, gérés par un administrateur (CdC § 6.6, Paramètres › Véhicules). */
final class VehiculesController extends Controller
{
    public function index(Chauffeur $chauffeur): JsonResponse
    {
        return ReponseApi::succes(VehiculeResource::collection(
            $chauffeur->vehicules()->with('type')->orderBy('id')->get(),
        ));
    }

    public function creer(Request $request, Chauffeur $chauffeur): JsonResponse
    {
        $saisie = $this->valider($request);

        $vehicule = $chauffeur->vehicules()->create($saisie);

        return ReponseApi::cree(new VehiculeResource($vehicule->load('type')), 'Véhicule créé.');
    }

    public function modifier(Request $request, Chauffeur $chauffeur, Vehicule $vehicule): JsonResponse
    {
        if ($vehicule->chauffeur_id !== $chauffeur->id) {
            abort(404);
        }

        $saisie = $request->validate([
            'type_vehicule_id' => ['sometimes', 'integer', Rule::exists('types_vehicule', 'id')],
            'immatriculation' => ['sometimes', 'string', 'max:20', Rule::unique('vehicules', 'immatriculation')->ignore($vehicule->id)],
            'actif' => ['sometimes', 'boolean'],
        ], [], ['type_vehicule_id' => 'type de véhicule', 'immatriculation' => 'immatriculation', 'actif' => 'actif']);

        $vehicule->update($saisie);

        return ReponseApi::succes(new VehiculeResource($vehicule->refresh()->load('type')), 'Véhicule modifié.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'type_vehicule_id' => ['required', 'integer', Rule::exists('types_vehicule', 'id')],
            'immatriculation' => ['required', 'string', 'max:20', Rule::unique('vehicules', 'immatriculation')],
            'actif' => ['sometimes', 'boolean'],
        ], [], ['type_vehicule_id' => 'type de véhicule', 'immatriculation' => 'immatriculation', 'actif' => 'actif']);
    }
}
