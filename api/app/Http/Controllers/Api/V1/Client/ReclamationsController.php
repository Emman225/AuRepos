<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Assistance\Services\Reclamations;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assistance\ReclamationResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mon espace › Réclamations (P2-AST-01, CdC § 6.1) : soulevées APRÈS un séjour terminé, sur
 * MON séjour uniquement, motif d'au moins 15 caractères (CdC, exact — revérifié côté service).
 */
final class ReclamationsController extends Controller
{
    public function __construct(private readonly Reclamations $reclamations) {}

    public function index(Request $request, string $reference): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);

        return ReponseApi::succes(ReclamationResource::collection(
            $sejour->reclamations()->orderByDesc('id')->get(),
        ));
    }

    public function soumettre(Request $request, string $reference): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);
        $saisie = $request->validate(['motif' => ['required', 'string', 'min:15', 'max:2000']], [], ['motif' => 'motif']);

        /** @var User $client */
        $client = $request->user();
        $reclamation = $this->reclamations->creer($sejour, $client, (string) $saisie['motif']);

        return ReponseApi::cree(new ReclamationResource($reclamation), 'Réclamation envoyée : la réception va l’instruire.');
    }

    private function leMien(Request $request, string $reference): Sejour
    {
        return Sejour::query()->where('reference', $reference)
            ->where('client_id', $request->user()?->getAuthIdentifier())
            ->firstOrFail();
    }
}
