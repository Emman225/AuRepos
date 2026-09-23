<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\DisponibiliteDeResidence;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\ResidenceProprietaireRequest;
use App\Http\Resources\Proprietaire\ResidenceResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Espace propriétaire › Mes résidences (CdC § 7.1 et § 10) : le propriétaire crée lui-même sa
 * résidence depuis son compte, la modifie, et commande le bouton « Occupée / Disponible ».
 */
final class ResidencesController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function __construct(private readonly DisponibiliteDeResidence $disponibilite) {}

    public function index(Request $request): JsonResponse
    {
        $residences = $this->monProprietaire($request)->residences()
            ->with('quartier.commune')
            ->withCount('logements')
            ->orderBy('nom')
            ->get();

        return ReponseApi::succes(ResidenceResource::collection($residences));
    }

    public function afficher(Request $request, Residence $residence): JsonResponse
    {
        $this->verifierAppartenance($request, $residence);

        return ReponseApi::succes(new ResidenceResource($residence->load('quartier.commune')->loadCount('logements')));
    }

    /** Rien de ce que le propriétaire saisit n'est visible du public tant qu'un administrateur ne l'a pas validé (CdC § 7.1). */
    public function creer(ResidenceProprietaireRequest $request): JsonResponse
    {
        $proprietaire = $this->monProprietaire($request);

        $residence = new Residence(['proprietaire_id' => $proprietaire->id]);
        $residence->fill(collect($request->validated())->except('equipements')->all())->save();

        if ($request->has('equipements')) {
            $residence->equipements()->sync($request->validated('equipements'));
        }

        return ReponseApi::cree(new ResidenceResource($residence->refresh()->load('quartier.commune')), 'Résidence créée.');
    }

    public function modifier(ResidenceProprietaireRequest $request, Residence $residence): JsonResponse
    {
        $this->verifierAppartenance($request, $residence);

        $residence->fill(collect($request->validated())->except('equipements')->all())->save();
        if ($request->has('equipements')) {
            $residence->equipements()->sync($request->validated('equipements'));
        }

        return ReponseApi::succes(new ResidenceResource($residence->refresh()->load('quartier.commune')), 'Résidence modifiée.');
    }

    /** Bouton « Occupée / Disponible » (CdC § 6.2 et § 10). */
    public function basculerLaDisponibilite(Request $request, Residence $residence): JsonResponse
    {
        $this->verifierAppartenance($request, $residence);

        $saisie = $request->validate([
            'disponibilite' => ['required', Rule::enum(Disponibilite::class)],
            'reouverture_prevue_le' => ['nullable', 'date', 'after:today'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['disponibilite' => 'disponibilité', 'reouverture_prevue_le' => 'date de réouverture prévue', 'motif' => 'motif']);

        /** @var User $auteur */
        $auteur = $request->user();
        $cible = Disponibilite::from($saisie['disponibilite']);

        $resultat = $this->disponibilite->basculer(
            $residence, $cible, $auteur,
            isset($saisie['reouverture_prevue_le']) ? Carbon::parse($saisie['reouverture_prevue_le']) : null,
            $saisie['motif'] ?? null,
        );

        return ReponseApi::succes([
            'disponibilite' => $residence->refresh()->disponibilite->value,
            'reouverture_prevue_le' => $residence->reouverture_prevue_le?->format('d/m/Y'),
            'sejours_a_honorer' => $resultat['sejours_a_honorer'],
        ], $cible === Disponibilite::Occupee
            ? 'Résidence fermée : elle n’apparaît plus sur le site.'.($resultat['sejours_a_honorer'] > 0 ? " {$resultat['sejours_a_honorer']} séjour(s) déjà confirmé(s) restent à honorer." : '')
            : 'Résidence rouverte : elle est de nouveau visible et réservable.');
    }

    /** Historique des fermetures et taux de disponibilité sur les 12 derniers mois (CdC § 6.2). */
    public function historiqueDisponibilite(Request $request, Residence $residence): JsonResponse
    {
        $this->verifierAppartenance($request, $residence);

        return ReponseApi::succes([
            'historique' => $this->disponibilite->historique($residence),
            // Jusqu'à MAINTENANT, pas « minuit ce matin » : une fermeture commencée aujourd'hui doit peser dans le taux dès sa première heure.
            'taux_disponibilite_12_mois' => $this->disponibilite->tauxDeDisponibilite($residence, Carbon::now()->subYear(), Carbon::now()),
        ]);
    }

    /** La résidence d'un autre propriétaire N'EXISTE PAS pour moi : 404, jamais 403. */
    private function verifierAppartenance(Request $request, Residence $residence): void
    {
        if ($residence->proprietaire_id !== $this->monProprietaire($request)->id) {
            abort(404);
        }
    }
}
