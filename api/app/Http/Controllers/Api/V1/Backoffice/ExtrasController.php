<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Extras\Models\Extra;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ExtraResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogue des extras (P2-EXT-01) : le CdC n'énumère aucune liste fixe — entièrement
 * modifiable par le back office, comme la carte d'un restaurateur
 * (App\Http\Controllers\Api\V1\Backoffice\ProduitsRepasController). Un extra désactivé
 * n'est jamais supprimé : les commandes déjà passées le référencent encore.
 */
final class ExtrasController extends Controller
{
    public function index(): JsonResponse
    {
        return ReponseApi::succes(ExtraResource::collection(Extra::query()->orderBy('nom')->get()));
    }

    public function creer(Request $request): JsonResponse
    {
        $extra = Extra::create($this->valider($request));

        return ReponseApi::cree(new ExtraResource($extra), 'Extra ajouté au catalogue.');
    }

    public function modifier(Request $request, Extra $extra): JsonResponse
    {
        $extra->update($this->valider($request));

        return ReponseApi::succes(new ExtraResource($extra->refresh()), 'Extra modifié.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'prix' => ['required', 'integer', 'min:0', 'max:100000000'],
            'actif' => ['nullable', 'boolean'],
        ], [], ['nom' => 'nom', 'description' => 'description', 'prix' => 'prix', 'actif' => 'actif']);
    }
}
