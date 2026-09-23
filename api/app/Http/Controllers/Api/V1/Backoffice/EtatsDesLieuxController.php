<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\TypeEtatDesLieux;
use App\Domain\Sejours\Models\EtatDesLieux;
use App\Domain\Sejours\Models\LigneEtatDesLieux;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\EtatsDesLieux;
use App\Http\Controllers\Controller;
use App\Http\Resources\Sejours\EtatDesLieuxResource;
use App\Http\Resources\Sejours\LigneEtatDesLieuxResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** États des lieux d'entrée et de sortie (P2-SEJ-02, CdC § 6.3). */
final class EtatsDesLieuxController extends Controller
{
    public function __construct(private readonly EtatsDesLieux $etatsDesLieux) {}

    public function index(Sejour $sejour): JsonResponse
    {
        return ReponseApi::succes(EtatDesLieuxResource::collection($sejour->etatsDesLieux()->with(['lignes', 'etablisseur'])->get()));
    }

    public function etablir(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'type' => ['required', Rule::enum(TypeEtatDesLieux::class)],
            'commentaire_general' => ['nullable', 'string', 'max:2000'],
        ], [], ['type' => 'type']);

        $etat = $this->etatsDesLieux->etablir($sejour, TypeEtatDesLieux::from($saisie['type']), $this->moi($request), $saisie['commentaire_general'] ?? null);

        return ReponseApi::cree(new EtatDesLieuxResource($etat->load('lignes')), 'État des lieux établi.');
    }

    public function ajouterUneLigne(Request $request, Sejour $sejour, EtatDesLieux $etatDesLieu): JsonResponse
    {
        $this->verifierAppartenance($sejour, $etatDesLieu);

        $saisie = $request->validate([
            'libelle' => ['required', 'string', 'max:150'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ], [], ['libelle' => 'libellé']);

        $ligne = $this->etatsDesLieux->ajouterUneLigne($etatDesLieu, $saisie);

        return ReponseApi::cree(new LigneEtatDesLieuxResource($ligne), 'Ligne ajoutée.');
    }

    public function ajouterUnePhoto(Request $request, Sejour $sejour, EtatDesLieux $etatDesLieu, LigneEtatDesLieux $ligne): JsonResponse
    {
        $this->verifierAppartenance($sejour, $etatDesLieu);
        if ($ligne->etat_des_lieux_id !== $etatDesLieu->id) {
            abort(404);
        }

        $saisie = $request->validate([
            'fichier' => ['required', File::types(['jpg', 'jpeg', 'png'])->max(5 * 1024)],
        ], [], ['fichier' => 'photo']);

        $this->etatsDesLieux->ajouterUnePhoto($ligne, $saisie['fichier'], $this->moi($request));

        return ReponseApi::succes(new LigneEtatDesLieuxResource($ligne->load('photos')), 'Photo ajoutée.');
    }

    public function signer(Request $request, Sejour $sejour, EtatDesLieux $etatDesLieu): JsonResponse
    {
        $this->verifierAppartenance($sejour, $etatDesLieu);

        $saisie = $request->validate(['signature' => ['required', 'string']], [], ['signature' => 'signature']);

        $etat = $this->etatsDesLieux->signer($etatDesLieu, $saisie['signature'], $this->moi($request));

        return ReponseApi::succes(new EtatDesLieuxResource($etat->load('lignes')), 'État des lieux signé.');
    }

    /** PDF une page, comparaison entrée / sortie, code-barres Code 128 de la référence (P2-SEJ-02). */
    public function pdf(Sejour $sejour): Response
    {
        return response($this->etatsDesLieux->pdf($sejour), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="etat-des-lieux-'.$sejour->reference.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Un état des lieux d'un autre séjour N'EXISTE PAS ici : 404, jamais 403. */
    private function verifierAppartenance(Sejour $sejour, EtatDesLieux $etatDesLieu): void
    {
        if ($etatDesLieu->sejour_id !== $sejour->id) {
            abort(404);
        }
    }

    private function moi(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }
}
