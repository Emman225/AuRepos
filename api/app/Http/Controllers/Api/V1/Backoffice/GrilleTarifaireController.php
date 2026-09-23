<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PourcentageEntreprise;
use App\Domain\Comptes\Models\User;
use App\Domain\Tarification\Models\LigneDeGrille;
use App\Domain\Tarification\Services\Tarifs;
use App\Domain\Tarification\Services\VerificationDeLaGrille;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ChangementAValiderResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Grille tarifaire nuitée : type (ou logement) × saison × tranche de durée (CdC § 7.3). Administrateurs seulement. */
final class GrilleTarifaireController extends Controller
{
    public function __construct(
        private readonly Tarifs $tarifs,
        private readonly PourcentageEntreprise $pourcentageEntreprise,
    ) {}

    /** La grille d'un type ou d'un logement, sous forme de tableau saisons × tranches. */
    public function afficher(Request $request): JsonResponse
    {
        $cible = $this->cible($request);
        $lignes = LigneDeGrille::query()->where($cible)->get();

        return ReponseApi::succes([
            'cible' => $cible,
            'tranches' => $this->tarifs->tranches()->map->only(['id', 'nom', 'nuits_min', 'nuits_max'])->values(),
            'saisons' => $this->tarifs->saisons()->map(fn ($s): array => [
                'id' => $s->id, 'nom' => $s->nom, 'categorie' => $s->categorie,
                'date_debut' => $s->date_debut->format('d/m/Y'), 'date_fin' => $s->date_fin->format('d/m/Y'),
                // Une case par tranche ; null = pas de tarif propre, le niveau suivant s'applique.
                'tarifs' => $this->tarifs->tranches()->mapWithKeys(fn ($t): array => [
                    $t->id => $lignes->first(fn (LigneDeGrille $l) => $l->saison_id === $s->id && $l->tranche_duree_id === $t->id)?->tarif,
                ]),
            ])->values(),
        ]);
    }

    /** Enregistre des cases de la grille ; un tarif nul EFFACE la case (le niveau suivant reprend la main). */
    public function enregistrer(Request $request): JsonResponse
    {
        $cible = $this->cible($request);
        $saisie = $request->validate([
            'lignes' => ['required', 'array', 'min:1', 'max:500'],
            'lignes.*.saison_id' => ['required', 'integer', Rule::exists('saisons', 'id')],
            'lignes.*.tranche_duree_id' => ['required', 'integer', Rule::exists('tranches_duree', 'id')],
            'lignes.*.tarif' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ], [], ['lignes.*.tarif' => 'tarif', 'lignes.*.saison_id' => 'saison', 'lignes.*.tranche_duree_id' => 'tranche de durée']);

        /** @var User $auteur */
        $auteur = $request->user();

        DB::transaction(function () use ($saisie, $cible, $auteur): void {
            foreach ($saisie['lignes'] as $ligne) {
                $cles = [...$cible, 'saison_id' => $ligne['saison_id'], 'tranche_duree_id' => $ligne['tranche_duree_id']];

                // Par le modèle (et non en masse) : chaque case laisse sa trace dans le journal d'audit.
                $ligne['tarif'] === null
                    ? LigneDeGrille::query()->where($cles)->get()->each->delete()
                    : LigneDeGrille::updateOrCreate($cles, ['tarif' => $ligne['tarif'], 'modifie_par' => $auteur->id]);
            }
        });

        return ReponseApi::succes(
            ['anomalies' => app(VerificationDeLaGrille::class)->anomalies()],
            'Grille enregistrée. Vérifiez-la sur une réservation réelle avant de vendre.',
        );
    }

    public function verifier(VerificationDeLaGrille $verification): JsonResponse
    {
        $anomalies = $verification->anomalies();
        $bloquantes = count(array_filter($anomalies, fn (array $a) => $a['niveau'] === 'bloquant'));

        return ReponseApi::succes(
            ['muette' => $bloquantes === 0, 'nombre_bloquantes' => $bloquantes, 'anomalies' => $anomalies],
            $bloquantes === 0 ? 'La grille est prête : aucune anomalie bloquante.' : "La grille n’est pas prête : {$bloquantes} anomalie(s) bloquante(s).",
        );
    }

    /** « Un changement de grille se vérifie sur une réservation réelle » (CdC § 12) : détail nuit par nuit. */
    public function simuler(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'logement_id' => ['required', 'integer', Rule::exists('logements', 'id')],
            'arrivee' => ['required', 'date'],
            'depart' => ['required', 'date', 'after:arrivee'],
        ], [], ['logement_id' => 'logement', 'arrivee' => 'date d’arrivée', 'depart' => 'date de départ']);

        $logement = Logement::findOrFail($saisie['logement_id']);
        $nuitees = $this->tarifs->nuitees($logement, Carbon::parse($saisie['arrivee']), Carbon::parse($saisie['depart']));
        $total = array_sum(array_column($nuitees, 'tarif'));

        return ReponseApi::succes([
            'logement' => $logement->reference,
            'nombre_de_nuits' => count($nuitees),
            'tranche' => $this->tarifs->tranchePour(count($nuitees))?->nom,
            'nuitees' => $nuitees,
            // Hébergement seul, hors taxes : remises, TVA, taxes et caution viennent avec le moteur de calcul (P1-TAR-07).
            'hebergement_hors_taxes' => $total,
            'marge_hebergement' => $logement->prix_proprietaire === null ? null : $total - $logement->prix_proprietaire * count($nuitees),
        ]);
    }

    /** Propose le taux global (CdC § 7.3) : il n'entre en vigueur qu'après validation par un second administrateur. */
    public function proposerLePourcentageEntreprise(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'taux' => ['required', 'numeric', 'min:0', 'max:500'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['taux' => 'taux', 'motif' => 'motif']);

        /** @var User $administrateur */
        $administrateur = $request->user();
        $changement = $this->pourcentageEntreprise->proposerLeTauxGlobal((float) $saisie['taux'], $saisie['motif'] ?? null, $administrateur);

        return ReponseApi::cree(
            new ChangementAValiderResource($changement),
            'Taux proposé. Il entrera en vigueur après validation par un second administrateur.',
        );
    }

    /** Bandeau permanent listant les logements en dérogation (CdC § 7.3). */
    public function derogations(): JsonResponse
    {
        return ReponseApi::succes(['derogations' => $this->pourcentageEntreprise->derogations()]);
    }

    /** @return array{type_logement_id: int}|array{logement_id: int} */
    private function cible(Request $request): array
    {
        $saisie = $request->validate([
            'type_logement_id' => ['nullable', 'integer', Rule::exists('types_logement', 'id'), 'required_without:logement_id', 'prohibits:logement_id'],
            'logement_id' => ['nullable', 'integer', Rule::exists('logements', 'id'), 'required_without:type_logement_id'],
        ], [
            'type_logement_id.prohibits' => 'Une grille vise un type OU un logement, pas les deux.',
        ], ['type_logement_id' => 'type de logement', 'logement_id' => 'logement']);

        return isset($saisie['logement_id'])
            ? ['logement_id' => (int) $saisie['logement_id']]
            : ['type_logement_id' => (int) $saisie['type_logement_id']];
    }
}
