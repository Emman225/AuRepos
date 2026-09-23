<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Transferts\Models\BaremeTransfert;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\BaremeTransfertResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Barème des transferts (CdC § 6.6) : zone (commune) × type de véhicule → prix. Administrateurs seulement. */
final class BaremesTransfertController extends Controller
{
    public function index(): JsonResponse
    {
        $baremes = BaremeTransfert::query()->with(['commune', 'typeVehicule'])->orderBy('commune_id')->orderBy('type_vehicule_id')->get();

        return ReponseApi::succes(BaremeTransfertResource::collection($baremes));
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $this->valider($request);

        $bareme = BaremeTransfert::create($saisie);

        return ReponseApi::cree(new BaremeTransfertResource($bareme->load(['commune', 'typeVehicule'])), 'Barème créé.');
    }

    public function modifier(Request $request, BaremeTransfert $bareme): JsonResponse
    {
        $saisie = $request->validate([
            'prix' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [], ['prix' => 'prix']);

        $bareme->update($saisie);

        return ReponseApi::succes(new BaremeTransfertResource($bareme->refresh()->load(['commune', 'typeVehicule'])), 'Barème modifié.');
    }

    public function supprimer(BaremeTransfert $bareme): JsonResponse
    {
        $bareme->delete();

        return ReponseApi::succes(null, 'Barème supprimé.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'commune_id' => ['required', 'integer', Rule::exists('communes', 'id')],
            'type_vehicule_id' => [
                'required', 'integer', Rule::exists('types_vehicule', 'id'),
                Rule::unique('baremes_transfert')->where(fn ($q) => $q->where('commune_id', $request->input('commune_id'))),
            ],
            'prix' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [], ['commune_id' => 'commune', 'type_vehicule_id' => 'type de véhicule', 'prix' => 'prix']);
    }
}
