<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Partenaires\Services\CreationDePartenaire;
use App\Domain\Transferts\Models\Chauffeur;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transferts\ChauffeurRequest;
use App\Http\Resources\Backoffice\ChauffeurResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Chauffeurs (CdC § 6.6) : liste, fiche, création — même patron que Backoffice\ApporteursController. */
final class ChauffeursController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'actif' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Chauffeur::query()
            ->with('utilisateur')
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $motif = '%'.addcslashes($v, '%_\\').'%';
                $q->whereHas('utilisateur', fn (Builder $u) => $u
                    ->where('nom', 'ilike', $motif)->orWhere('prenoms', 'ilike', $motif)->orWhere('email', 'ilike', $motif));
            })
            ->when(array_key_exists('actif', $filtres) && $filtres['actif'] !== null,
                fn (Builder $q) => $q->where('actif', (bool) $filtres['actif']))
            ->orderBy('id')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ChauffeurResource::class);
    }

    public function creer(ChauffeurRequest $request, CreationDePartenaire $creation): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();

        $chauffeur = $creation->chauffeur($compte, $fiche);

        return ReponseApi::cree(
            new ChauffeurResource($chauffeur->load('utilisateur')),
            'Chauffeur créé. Un courriel l’invite à choisir son mot de passe.',
        );
    }

    public function modifier(ChauffeurRequest $request, Chauffeur $chauffeur): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();

        DB::transaction(function () use ($chauffeur, $compte, $fiche): void {
            if ($compte !== []) {
                $chauffeur->utilisateur->update($compte);
            }
            if ($fiche !== []) {
                $chauffeur->update($fiche);
            }
        });

        return ReponseApi::succes(new ChauffeurResource($chauffeur->refresh()->load('utilisateur')), 'Chauffeur modifié.');
    }
}
