<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Models\Diapositive;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\DiapositiveResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Carrousel de la page d'accueil (CdC § 12, écran Paramètres › Divers, P1-BO-10). */
final class CarrouselController extends Controller
{
    public function index(): JsonResponse
    {
        $diapositives = Diapositive::query()->orderBy('ordre')->orderBy('id')->get();

        return ReponseApi::succes(DiapositiveResource::collection($diapositives));
    }

    /** Export Excel / Word / PDF de la liste, même filtre que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $request->validate([
            'actif' => ['nullable', 'boolean'],
            'format' => ['required', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);

        $diapositives = Diapositive::query()
            ->when(array_key_exists('actif', $filtres) && $filtres['actif'] !== null, fn ($q) => $q->where('actif', (bool) $filtres['actif']))
            ->orderBy('ordre')->orderBy('id')->get();

        $export = new ExportDeListe('Carrousel', [
            'legende' => 'Légende', 'ordre' => 'Ordre', 'actif' => 'Actif',
        ], $diapositives->map(fn (Diapositive $d): array => [
            'legende' => $d->legende ?? '',
            'ordre' => (string) $d->ordre,
            'actif' => $d->actif ? 'Oui' : 'Non',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $this->valider($request);

        /** @var User $auteur */
        $auteur = $request->user();
        $saisie['cree_par'] = $auteur->id;

        $diapositive = Diapositive::create($saisie);

        return ReponseApi::cree(new DiapositiveResource($diapositive), 'Diapositive créée.');
    }

    public function modifier(Request $request, Diapositive $diapositive): JsonResponse
    {
        $diapositive->update($this->valider($request));

        return ReponseApi::succes(new DiapositiveResource($diapositive->refresh()), 'Diapositive modifiée.');
    }

    public function supprimer(Diapositive $diapositive): JsonResponse
    {
        $diapositive->delete();

        return ReponseApi::succes(null, 'Diapositive supprimée.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'image_url' => ['sometimes', 'required', 'string', 'max:500'],
            'legende' => ['sometimes', 'nullable', 'string', 'max:150'],
            'lien' => ['sometimes', 'nullable', 'string', 'max:500'],
            'ordre' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'actif' => ['sometimes', 'boolean'],
        ], [], [
            'image_url' => 'image', 'legende' => 'légende', 'lien' => 'lien', 'ordre' => 'ordre', 'actif' => 'actif',
        ]);
    }
}
