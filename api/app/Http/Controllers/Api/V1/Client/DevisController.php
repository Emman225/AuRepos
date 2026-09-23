<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Devis;
use App\Domain\Sejours\Services\GestionDesDevis;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sejours\DevisRequest;
use App\Http\Requests\Sejours\TransformationDevisRequest;
use App\Http\Resources\Client\DevisResource;
use App\Http\Resources\Client\SejourResource;
use App\Support\Api\ErreurMetier;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace client › Mes devis (CdC § 5.1, § 5.3) : devis en cours, transformation, suppression. */
final class DevisController extends Controller
{
    private const RELATIONS = ['logement.residence.quartier.commune'];

    public function __construct(private readonly GestionDesDevis $devis) {}

    public function index(Request $request): JsonResponse
    {
        $page = Devis::query()->with(self::RELATIONS)
            ->where('client_id', $request->user()?->getAuthIdentifier())
            ->orderByDesc('id')
            ->paginate(min(50, max(5, (int) $request->query('par_page', 20))));

        return ReponseApi::page($page, DevisResource::class);
    }

    public function afficher(Request $request, string $reference): JsonResponse
    {
        return ReponseApi::succes(new DevisResource($this->leMien($request, $reference)));
    }

    public function creer(DevisRequest $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user();
        /** @var array<string, mixed> $saisie */
        $saisie = $request->validated();

        $logement = Logement::query()->where('reference', $saisie['reference_logement'])->first()
            ?? throw new ErreurMetier('Ce logement n’est pas ouvert à la réservation.', 'logement_non_reservable', 422);

        $devis = $this->devis->etablir($client, $logement, $saisie);

        return ReponseApi::cree(new DevisResource($devis->load(self::RELATIONS)), 'Devis établi. Les prix sont figés : transformez-le en réservation quand vous le souhaitez.');
    }

    public function transformer(TransformationDevisRequest $request, string $reference): JsonResponse
    {
        $devis = $this->leMien($request, $reference);
        /** @var array<string, mixed> $saisie */
        $saisie = $request->validated();

        $sejour = $this->devis->transformer($devis, $saisie);

        return ReponseApi::cree(
            new SejourResource($sejour->load(['logement.type', 'logement.residence.quartier.commune', 'occupants'])),
            'Devis transformé en réservation.',
        );
    }

    /** « Se supprime » (CdC § 5.1) : archivé, jamais effacé. */
    public function archiver(Request $request, string $reference): JsonResponse
    {
        $devis = $this->leMien($request, $reference);
        $this->devis->archiver($devis);

        return ReponseApi::succes(new DevisResource($devis->refresh()->load(self::RELATIONS)), 'Devis archivé.');
    }

    /** Le devis d'un autre client N'EXISTE PAS pour moi : 404, jamais 403. */
    private function leMien(Request $request, string $reference): Devis
    {
        return Devis::query()->with(self::RELATIONS)
            ->where('reference', $reference)
            ->where('client_id', $request->user()?->getAuthIdentifier())
            ->firstOrFail();
    }
}
