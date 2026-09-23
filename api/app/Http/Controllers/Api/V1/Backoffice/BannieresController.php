<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Models\Banniere;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\BanniereResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Bannières promotionnelles de la page d'accueil (CdC § 5.1, écran Paramètres › Divers, P1-BO-10). */
final class BannieresController extends Controller
{
    public function index(): JsonResponse
    {
        $bannieres = Banniere::query()->orderBy('ordre')->orderBy('id')->get();

        return ReponseApi::succes(BanniereResource::collection($bannieres));
    }

    /** Export Excel / Word / PDF de la liste, même filtre que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $request->validate([
            'actif' => ['nullable', 'boolean'],
            'format' => ['required', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);

        $bannieres = Banniere::query()
            ->when(array_key_exists('actif', $filtres) && $filtres['actif'] !== null, fn ($q) => $q->where('actif', (bool) $filtres['actif']))
            ->orderBy('ordre')->orderBy('id')->get();

        $export = new ExportDeListe('Bannieres', [
            'titre' => 'Titre', 'ordre' => 'Ordre', 'actif' => 'Actif',
        ], $bannieres->map(fn (Banniere $b): array => [
            'titre' => $b->titre,
            'ordre' => (string) $b->ordre,
            'actif' => $b->actif ? 'Oui' : 'Non',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $this->valider($request);

        /** @var User $auteur */
        $auteur = $request->user();
        $saisie['cree_par'] = $auteur->id;

        $banniere = Banniere::create($saisie);

        return ReponseApi::cree(new BanniereResource($banniere), 'Bannière créée.');
    }

    public function modifier(Request $request, Banniere $banniere): JsonResponse
    {
        $banniere->update($this->valider($request));

        return ReponseApi::succes(new BanniereResource($banniere->refresh()), 'Bannière modifiée.');
    }

    public function supprimer(Banniere $banniere): JsonResponse
    {
        $banniere->delete();

        return ReponseApi::succes(null, 'Bannière supprimée.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'titre' => ['sometimes', 'required', 'string', 'max:150'],
            'sous_titre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_url' => ['sometimes', 'required', 'string', 'max:500'],
            'lien' => ['sometimes', 'nullable', 'string', 'max:500'],
            'ordre' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'actif' => ['sometimes', 'boolean'],
        ], [], [
            'titre' => 'titre', 'sous_titre' => 'sous-titre', 'image_url' => 'image',
            'lien' => 'lien', 'ordre' => 'ordre', 'actif' => 'actif',
        ]);
    }
}
