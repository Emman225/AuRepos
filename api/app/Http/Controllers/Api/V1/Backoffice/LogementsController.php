<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\LogementRequest;
use App\Http\Resources\Backoffice\LogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue › Logements d'une résidence (back office).
 *
 * Prix et état de publication ne se changent PAS ici : ils ont leurs propres
 * circuits (négociation et double validation — P1-CAT-05 ; publication — P3-PUB).
 */
final class LogementsController extends Controller
{
    public function index(Residence $residence): JsonResponse
    {
        return ReponseApi::succes(LogementResource::collection(
            $residence->logements()->with(['type', 'equipements'])->orderBy('nom')->get(),
        ));
    }

    public function afficher(Residence $residence, Logement $logement): JsonResponse
    {
        return ReponseApi::succes(new LogementResource($logement->load(['type', 'equipements'])));
    }

    public function creer(LogementRequest $request, Residence $residence): JsonResponse
    {
        $logement = new Logement(['residence_id' => $residence->id]);
        // Celui qui saisit un logement au nom d'un propriétaire ne pourra pas le publier lui-même (CdC § 7.1).
        $logement->forceFill(['cree_par' => $request->user()?->getAuthIdentifier()]);

        return ReponseApi::cree(
            new LogementResource($this->enregistrer($logement, $request->validated())),
            'Logement créé en brouillon.',
        );
    }

    public function modifier(LogementRequest $request, Residence $residence, Logement $logement): JsonResponse
    {
        return ReponseApi::succes(
            new LogementResource($this->enregistrer($logement, $request->validated())),
            'Logement modifié.',
        );
    }

    public function supprimer(Residence $residence, Logement $logement): JsonResponse
    {
        // Suppression douce : les séjours passés gardent leur logement.
        $logement->delete();

        return ReponseApi::succes(null, 'Logement supprimé.');
    }

    /** @param array<string, mixed> $saisie */
    private function enregistrer(Logement $logement, array $saisie): Logement
    {
        DB::transaction(function () use ($logement, $saisie): void {
            $logement->fill(Arr::except($saisie, 'equipements'))->save();

            if (array_key_exists('equipements', $saisie)) {
                $logement->equipements()->sync($saisie['equipements']);
            }
        });

        return $logement->refresh()->load(['type', 'equipements']);
    }
}
