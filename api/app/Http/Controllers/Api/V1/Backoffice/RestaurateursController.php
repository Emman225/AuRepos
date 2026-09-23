<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Services\CreationDePartenaire;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Domain\Validation\Services\DoubleValidation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Repas\RestaurateurRequest;
use App\Http\Resources\Backoffice\ChangementAValiderResource;
use App\Http\Resources\Repas\RestaurateurResource;
use App\Support\Api\ErreurMetier;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Restaurateurs partenaires › liste, fiche, double validation du pourcentage plateforme,
 * dette (CdC — « Repas et boissons »). Même patron que ApporteursController.
 */
final class RestaurateursController extends Controller
{
    public function __construct(private readonly DoubleValidation $doubleValidation) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'actif' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Restaurateur::query()
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

        return ReponseApi::page($page, RestaurateurResource::class);
    }

    public function creer(RestaurateurRequest $request, CreationDePartenaire $creation): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();

        $restaurateur = $creation->restaurateur($compte, $fiche);

        return ReponseApi::cree(
            new RestaurateurResource($restaurateur->load('utilisateur')),
            'Restaurateur créé. Un courriel l’invite à choisir son mot de passe.',
        );
    }

    public function modifier(RestaurateurRequest $request, Restaurateur $restaurateur): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();

        DB::transaction(function () use ($restaurateur, $compte, $fiche): void {
            if ($compte !== []) {
                $restaurateur->utilisateur->update($compte);
            }
            $restaurateur->update($fiche);
        });

        return ReponseApi::succes(new RestaurateurResource($restaurateur->refresh()->load('utilisateur')), 'Restaurateur modifié.');
    }

    /**
     * Propose un nouveau pourcentage plateforme : il n'entre en vigueur qu'après validation
     * par un second administrateur, via la file générique (PUT backoffice/changements/{id}/decision).
     */
    public function proposerLePourcentage(Request $request, Restaurateur $restaurateur): JsonResponse
    {
        $saisie = $request->validate([
            'pourcentage' => ['required', 'numeric', 'min:0', 'max:500'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['pourcentage' => 'pourcentage plateforme', 'motif' => 'motif']);

        /** @var User $administrateur */
        $administrateur = $request->user();

        $changement = $this->doubleValidation->proposer(
            $restaurateur, 'pourcentage_plateforme', (float) $saisie['pourcentage'], $administrateur, $saisie['motif'] ?? null,
            function (mixed $actuel, mixed $propose): void {
                if ((float) $propose < 0 || (float) $propose > 500) {
                    throw new ErreurMetier('Le pourcentage plateforme doit être compris entre 0 et 500 %.', 'pourcentage_hors_limites', 422);
                }
            },
        );

        return ReponseApi::cree(
            new ChangementAValiderResource($changement),
            'Pourcentage proposé. Il entrera en vigueur après validation par un second administrateur.',
        );
    }

    /** Ce qui est dû au restaurateur (CdC — « Dette › Restaurateurs »). */
    public function dette(Restaurateur $restaurateur, GestionDesCommandes $commandes): JsonResponse
    {
        return ReponseApi::succes($commandes->detteEnversLeRestaurateur($restaurateur));
    }
}
