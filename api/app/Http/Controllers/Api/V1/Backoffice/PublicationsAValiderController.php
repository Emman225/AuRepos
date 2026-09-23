<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\LogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * File « Publications à valider » (CdC § 7.1, logigramme 6) : les logements qu'un propriétaire
 * a soumis et qui attendent qu'un administrateur les publie ou les refuse — toutes résidences
 * confondues (un gestionnaire ne voit que les siennes, CdC § 9.5). Sa pastille est le compte.
 */
final class PublicationsAValiderController extends Controller
{
    public function __construct(private readonly PerimetreGestionnaire $perimetre) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->requete($request)
            ->with(['type', 'residence.proprietaire.utilisateur', 'residence.quartier.commune'])
            ->orderBy('created_at')
            ->paginate((int) $request->query('par_page', 25));

        return ReponseApi::page($page, LogementResource::class);
    }

    /** Le nombre pour la pastille — jamais besoin de charger toute la liste pour l'afficher. */
    public function compte(Request $request): JsonResponse
    {
        return ReponseApi::succes(['nombre' => $this->requete($request)->count()]);
    }

    /** @return Builder<Logement> */
    private function requete(Request $request): Builder
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $residencesAutorisees = $this->perimetre->residencesAutorisees($utilisateur);

        return Logement::query()->where('etat_publication', EtatPublication::EnAttente)
            ->when($residencesAutorisees !== null, fn (Builder $q) => $q->whereIn('residence_id', $residencesAutorisees ?? []));
    }
}
