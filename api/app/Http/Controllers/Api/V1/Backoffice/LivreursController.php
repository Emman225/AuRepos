<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Partenaires\Services\CreationDePartenaire;
use App\Domain\Repas\Models\Livreur;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Repas\LivreurRequest;
use App\Http\Resources\Repas\LivreurResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Livreurs de repas › liste, fiche, gains (CdC — « Repas et boissons », espace livreur). */
final class LivreursController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'actif' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Livreur::query()
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

        return ReponseApi::page($page, LivreurResource::class);
    }

    public function creer(LivreurRequest $request, CreationDePartenaire $creation): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();

        $livreur = $creation->livreur($compte, $fiche);

        return ReponseApi::cree(
            new LivreurResource($livreur->load('utilisateur')),
            'Livreur créé. Un courriel l’invite à choisir son mot de passe.',
        );
    }

    public function modifier(LivreurRequest $request, Livreur $livreur): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();

        DB::transaction(function () use ($livreur, $compte, $fiche): void {
            if ($compte !== []) {
                $livreur->utilisateur->update($compte);
            }
            $livreur->update($fiche);
        });

        return ReponseApi::succes(new LivreurResource($livreur->refresh()->load('utilisateur')), 'Livreur modifié.');
    }

    public function gains(Livreur $livreur, GestionDesCommandes $commandes): JsonResponse
    {
        return ReponseApi::succes($commandes->gainsDuLivreur($livreur));
    }
}
