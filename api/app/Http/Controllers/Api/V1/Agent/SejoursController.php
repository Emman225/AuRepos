<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Services\PiecesJustificatives;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Occupant;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\CheckIn;
use App\Domain\Sejours\Services\CheckOut;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\SejourResource;
use App\Http\Resources\Sejours\OccupantResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\File;

/**
 * Espace agent de terrain (self-service, CdC § 6.3) : check-in, fiche de police, check-out.
 * L'agent n'est pas un partenaire commissionné (pas de fiche métier dédiée, contrairement au
 * chauffeur ou au restaurateur) : juste du personnel affecté à des séjours (`User` +
 * `Profil::AgentTerrain`) — il agit sur le séjour qu'on lui donne, sans périmètre par résidence.
 * Il SAISIT le code d'arrivée, il ne le lit jamais (CdC § 11).
 */
final class SejoursController extends Controller
{
    private const RELATIONS = ['client', 'logement.residence'];

    public function __construct(
        private readonly CheckIn $checkIn,
        private readonly CheckOut $checkOut,
    ) {}

    /** Mes arrivées et départs du jour (P2-BO-02, vu côté agent) : confirmés à accueillir, arrivés à faire partir. */
    public function index(Request $request): JsonResponse
    {
        $aujourdhui = Carbon::today()->toDateString();

        $sejours = Sejour::query()->with(self::RELATIONS)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q2) => $q2->where('etat', EtatDuSejour::Confirme->value)->whereDate('arrivee', '<=', $aujourdhui))
                ->orWhere(fn (Builder $q2) => $q2->where('etat', EtatDuSejour::Arrive->value)->whereDate('depart', '<=', $aujourdhui)))
            ->orderBy('arrivee')->get();

        return ReponseApi::succes(SejourResource::collection($sejours));
    }

    public function afficher(Sejour $sejour): JsonResponse
    {
        return ReponseApi::succes(new SejourResource($sejour->load([...self::RELATIONS, 'occupants'])));
    }

    public function occupants(Sejour $sejour): JsonResponse
    {
        return ReponseApi::succes(OccupantResource::collection($sejour->occupants()->with('pieces')->get()));
    }

    public function checkIn(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'occupants' => ['nullable', 'array'],
            'occupants.*.nom' => ['required_with:occupants', 'string', 'max:100'],
            'occupants.*.prenoms' => ['nullable', 'string', 'max:150'],
            'occupants.*.enfant' => ['nullable', 'boolean'],
            'occupants.*.type_piece' => ['nullable', 'string', 'max:30'],
            'occupants.*.numero_piece' => ['nullable', 'string', 'max:100'],
            'occupants.*.telephone' => ['nullable', 'string', 'max:30'],
        ], [], ['code' => 'code d’arrivée']);

        $sejour = $this->checkIn->effectuer($sejour, $saisie['code'], $this->moi($request), $saisie['occupants'] ?? []);

        return ReponseApi::succes(new SejourResource($sejour->load([...self::RELATIONS, 'occupants'])), 'Check-in effectué : le séjour est arrivé.');
    }

    public function consommations(Sejour $sejour): JsonResponse
    {
        return ReponseApi::succes($this->checkOut->consommations($sejour));
    }

    public function checkOutSejour(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'caution_retenue' => ['required', 'integer', 'min:0'],
            'motif' => ['nullable', 'string', 'min:5', 'max:255'],
        ], [], ['caution_retenue' => 'caution retenue', 'motif' => 'motif']);

        $sejour = $this->checkOut->effectuer($sejour, $this->moi($request), (int) $saisie['caution_retenue'], $saisie['motif'] ?? null);

        return ReponseApi::succes(new SejourResource($sejour->load(self::RELATIONS)), 'Check-out effectué : le séjour est parti.');
    }

    /** Photo de la pièce d'identité d'un occupant (fiche de police, P2-SEJ-01) : chiffrée, jamais un second mécanisme. */
    public function deposerLaPieceDUnOccupant(Request $request, Sejour $sejour, Occupant $occupant, PiecesJustificatives $pieces): JsonResponse
    {
        $saisie = $request->validate([
            'fichier' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5 * 1024)],
        ], [], ['fichier' => 'photo de la pièce']);

        $pieces->deposer($occupant, TypeDePiece::PieceIdentite, $saisie['fichier'], null, $this->moi($request));

        return ReponseApi::succes(new OccupantResource($occupant->load('pieces')), 'Pièce d’identité déposée.');
    }

    private function moi(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }
}
