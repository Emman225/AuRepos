<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\DisponibiliteDeResidence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\BlocageCalendrier;
use App\Domain\Sejours\Services\Calendrier;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Blocage de dates d'un logement et fermeture d'une résidence (CdC § 6.2). */
final class CalendrierController extends Controller
{
    public function __construct(
        private readonly Calendrier $calendrier,
        private readonly DisponibiliteDeResidence $disponibilite,
    ) {}

    public function blocages(Residence $residence, Logement $logement): JsonResponse
    {
        return ReponseApi::succes(
            BlocageCalendrier::query()->where('logement_id', $logement->id)->where('fin', '>=', Carbon::today())->orderBy('debut')->get()
                ->map(fn (BlocageCalendrier $b): array => $this->presenter($b)),
        );
    }

    public function bloquer(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $saisie = $request->validate([
            'debut' => ['required', 'date', 'after_or_equal:today'],
            'fin' => ['required', 'date', 'after_or_equal:debut'],
            // `canal_externe` est posé par la synchronisation des canaux, jamais à la main.
            'motif' => ['required', Rule::in(['maintenance', 'usage_proprietaire', 'saison_fermee'])],
            'commentaire' => ['nullable', 'string', 'max:255'],
        ], [], ['debut' => 'date de début', 'fin' => 'date de fin', 'motif' => 'motif', 'commentaire' => 'commentaire']);

        /** @var User $auteur */
        $auteur = $request->user();
        $blocage = $this->calendrier->bloquer(
            $logement, Carbon::parse($saisie['debut']), Carbon::parse($saisie['fin']), $saisie['motif'], $saisie['commentaire'] ?? null, $auteur,
        );

        return ReponseApi::cree($this->presenter($blocage), 'Dates bloquées : elles sont retirées de la vente.');
    }

    public function debloquer(Residence $residence, Logement $logement, BlocageCalendrier $blocage): JsonResponse
    {
        $this->calendrier->debloquer($blocage);

        return ReponseApi::succes(null, 'Dates rendues à la vente.');
    }

    /**
     * Bouton « Occupée / Disponible » : fermée, la résidence disparaît aussitôt de la recherche publique
     * et ne peut plus être réservée ; les séjours déjà confirmés restent honorés.
     */
    public function disponibilite(Request $request, Residence $residence): JsonResponse
    {
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

    /** @return array<string, mixed> */
    private function presenter(BlocageCalendrier $b): array
    {
        return [
            'id' => $b->id, 'debut' => $b->debut->format('Y-m-d'), 'fin' => $b->fin->format('Y-m-d'),
            'motif' => $b->motif, 'motif_libelle' => BlocageCalendrier::MOTIFS[$b->motif] ?? $b->motif, 'commentaire' => $b->commentaire,
        ];
    }
}
