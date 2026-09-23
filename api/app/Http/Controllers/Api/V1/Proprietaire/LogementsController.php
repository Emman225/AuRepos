<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PublicationDeLogement;
use App\Domain\Catalogue\Services\VersionsDeLogement;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\LogementProprietaireRequest;
use App\Http\Resources\Backoffice\VersionLogementResource;
use App\Http\Resources\Proprietaire\LogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace propriétaire › Logements d'une de SES résidences (CdC § 7.1) : création, modification
 * (avec version en attente une fois publié, P3-PUB-04), soumission à validation.
 */
final class LogementsController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function __construct(
        private readonly PublicationDeLogement $publication,
        private readonly VersionsDeLogement $versions,
    ) {}

    public function index(Request $request, Residence $residence): JsonResponse
    {
        $this->verifierAppartenance($request, $residence);

        return ReponseApi::succes(LogementResource::collection(
            $residence->logements()->with('type')->orderBy('nom')->get(),
        ));
    }

    public function afficher(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        return ReponseApi::succes(new LogementResource($logement->load('type')));
    }

    /** Créé en brouillon (CdC § 7.1) : rien n'est visible du public tant qu'un administrateur n'a pas validé. */
    public function creer(LogementProprietaireRequest $request, Residence $residence): JsonResponse
    {
        $this->verifierAppartenance($request, $residence);

        $logement = new Logement(['residence_id' => $residence->id]);
        /** @var User $auteur */
        $auteur = $request->user();
        // Un logement saisi par le PROPRIÉTAIRE lui-même n'a pas la contrainte « pas sa propre
        // saisie » de PublicationDeLogement::publier — celle-ci ne s'applique qu'au personnel.
        $logement->fill(collect($request->validated())->except('equipements')->all())->save();
        if ($request->has('equipements')) {
            $logement->equipements()->sync($request->validated('equipements'));
        }

        return ReponseApi::cree(new LogementResource($logement->refresh()->load('type')), 'Logement créé en brouillon.');
    }

    /** Un logement PUBLIÉ ne se modifie pas en place : la version publiée reste en ligne (P3-PUB-04). */
    public function modifier(LogementProprietaireRequest $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        if ($this->versions->logementPublie($logement)) {
            /** @var User $auteur */
            $auteur = $request->user();
            $version = $this->versions->proposer($logement, collect($request->validated())->except('equipements')->all(), $auteur);

            return ReponseApi::cree(
                new VersionLogementResource($version->load('auteur')),
                'Logement publié : la modification attend sa validation. La version publiée reste en ligne.',
            );
        }

        $logement->fill(collect($request->validated())->except('equipements')->all())->save();
        if ($request->has('equipements')) {
            $logement->equipements()->sync($request->validated('equipements'));
        }

        return ReponseApi::succes(new LogementResource($logement->refresh()->load('type')), 'Logement modifié.');
    }

    /** Soumission à la validation (CdC § 7.1) — aussi la RESOUMISSION après un refus, une fois corrigé. */
    public function soumettre(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        /** @var User $auteur */
        $auteur = $request->user();
        $this->publication->soumettre($logement, $auteur);

        return ReponseApi::succes(new LogementResource($logement->refresh()->load('type')), 'Logement soumis à la validation.');
    }

    /** Ce qui empêche encore la mise en ligne — et, s'il a été refusé, les motifs par champ (CdC § 7.1). */
    public function publication(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        return ReponseApi::succes([
            'etat' => $logement->etat_publication->value,
            'etat_libelle' => $logement->etat_publication->libelle(),
            'motif' => $logement->getAttribute('motif_refus'),
            'motifs_refus_champs' => $logement->motifs_refus_champs,
            'obstacles' => $this->publication->obstacles($logement),
        ]);
    }

    /** La résidence d'un autre propriétaire N'EXISTE PAS pour moi : 404, jamais 403. */
    private function verifierAppartenance(Request $request, Residence $residence): void
    {
        if ($residence->proprietaire_id !== $this->monProprietaire($request)->id) {
            abort(404);
        }
    }

    private function verifierLeLogement(Request $request, Residence $residence, Logement $logement): void
    {
        $this->verifierAppartenance($request, $residence);
        if ($logement->residence_id !== $residence->id) {
            abort(404);
        }
    }
}
