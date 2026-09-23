<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Models\VersionLogement;
use App\Domain\Catalogue\Services\VersionsDeLogement;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\LogementRequest;
use App\Http\Resources\Backoffice\LogementResource;
use App\Http\Resources\Backoffice\VersionLogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __construct(private readonly VersionsDeLogement $versions) {}

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

    /**
     * Un logement PUBLIÉ ne se modifie pas en place : la modification attend sa validation,
     * la version publiée restant en ligne entre-temps (CdC § 7.1, P3-PUB-04).
     */
    public function modifier(LogementRequest $request, Residence $residence, Logement $logement): JsonResponse
    {
        if ($this->versions->logementPublie($logement)) {
            /** @var User $auteur */
            $auteur = $request->user();
            $version = $this->versions->proposer($logement, $request->validated(), $auteur);

            return ReponseApi::cree(
                new VersionLogementResource($version->load('auteur')),
                'Logement publié : la modification attend sa validation. La version publiée reste en ligne.',
            );
        }

        return ReponseApi::succes(
            new LogementResource($this->enregistrer($logement, $request->validated())),
            'Logement modifié.',
        );
    }

    /** Ce qui attend sa validation sur ce logement, s'il y a lieu (P3-PUB-04). */
    public function versionEnAttente(Residence $residence, Logement $logement): JsonResponse
    {
        $version = $this->versions->enAttente($logement);

        return ReponseApi::succes($version ? new VersionLogementResource($version->load('auteur')) : null);
    }

    public function validerLaVersion(Request $request, Residence $residence, Logement $logement, VersionLogement $version): JsonResponse
    {
        /** @var User $administrateur */
        $administrateur = $request->user();
        $this->versions->valider($version, $administrateur);

        return ReponseApi::succes(new LogementResource($logement->refresh()->load(['type', 'equipements'])), 'Modification validée.');
    }

    public function refuserLaVersion(Request $request, Residence $residence, Logement $logement, VersionLogement $version): JsonResponse
    {
        $saisie = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:255']], [], ['motif' => 'motif']);

        /** @var User $administrateur */
        $administrateur = $request->user();
        $this->versions->refuser($version, $administrateur, $saisie['motif']);

        return ReponseApi::succes(null, 'Modification refusée.');
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
