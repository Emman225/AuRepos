<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\GestionDesAvis;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\AvisResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace client › Déposer un avis sur un séjour terminé (CdC § 5.1, P2-AVI-01). */
final class AvisController extends Controller
{
    public function __construct(private readonly GestionDesAvis $avis) {}

    public function soumettre(Request $request, string $reference): JsonResponse
    {
        $saisie = $request->validate([
            'note' => ['required', 'integer', 'min:1', 'max:5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ], [], ['note' => 'note', 'commentaire' => 'commentaire']);

        // Le séjour d'un autre client N'EXISTE PAS pour moi : 404, jamais 403.
        $sejour = Sejour::query()->where('reference', $reference)->where('client_id', $request->user()?->getAuthIdentifier())->firstOrFail();

        $avis = $this->avis->soumettre($sejour, (int) $saisie['note'], $saisie['commentaire'] ?? null);

        return ReponseApi::cree(new AvisResource($avis), 'Merci pour votre avis : il sera publié après vérification.');
    }
}
