<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\BlocageCalendrier;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\Calendrier;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Blocage de dates d'un logement et fermeture d'une résidence (CdC § 6.2). */
final class CalendrierController extends Controller
{
    public function __construct(private readonly Calendrier $calendrier) {}

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
    public function disponibilite(Request $request, Residence $residence, JournalAudit $journal): JsonResponse
    {
        $saisie = $request->validate([
            'disponibilite' => ['required', Rule::enum(Disponibilite::class)],
            'reouverture_prevue_le' => ['nullable', 'date', 'after:today'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['disponibilite' => 'disponibilité', 'reouverture_prevue_le' => 'date de réouverture prévue', 'motif' => 'motif']);

        /** @var User $auteur */
        $auteur = $request->user();
        $cible = Disponibilite::from($saisie['disponibilite']);

        if ($cible !== $residence->disponibilite) {
            DB::transaction(function () use ($residence, $cible, $saisie, $auteur, $journal): void {
                $fermee = $cible === Disponibilite::Occupee;

                $fermee
                    ? DB::table('fermetures_residence')->insert([
                        'residence_id' => $residence->id, 'fermee_le' => now(), 'fermee_par' => $auteur->id, 'motif' => $saisie['motif'] ?? null,
                        'reouverture_prevue_le' => $saisie['reouverture_prevue_le'] ?? null, 'created_at' => now(), 'updated_at' => now(),
                    ])
                    : DB::table('fermetures_residence')->where('residence_id', $residence->id)->whereNull('rouverte_le')
                        ->update(['rouverte_le' => now(), 'rouverte_par' => $auteur->id, 'updated_at' => now()]);

                $residence->forceFill([
                    'disponibilite' => $cible,
                    'reouverture_prevue_le' => $fermee ? ($saisie['reouverture_prevue_le'] ?? null) : null,
                ])->saveQuietly();

                $journal->consigner(
                    $fermee ? 'residence_fermee' : 'residence_rouverte',
                    ($fermee ? 'Fermeture (« Occupée ») : ' : 'Réouverture (« Disponible ») : ').$residence->libelleAudit().'.',
                    $residence, auteur: $auteur,
                );
            });
        }

        // À afficher AVANT de confirmer une fermeture : ces séjours restent dus.
        $aHonorer = Sejour::query()
            ->whereIn('logement_id', $residence->logements()->pluck('id'))
            ->whereIn('etat', [EtatDuSejour::Confirme, EtatDuSejour::Arrive])
            ->where('depart', '>=', Carbon::today())->count();

        return ReponseApi::succes([
            'disponibilite' => $residence->refresh()->disponibilite->value,
            'reouverture_prevue_le' => $residence->reouverture_prevue_le?->format('d/m/Y'),
            'sejours_a_honorer' => $aHonorer,
        ], $cible === Disponibilite::Occupee
            ? 'Résidence fermée : elle n’apparaît plus sur le site.'.($aHonorer > 0 ? " {$aHonorer} séjour(s) déjà confirmé(s) restent à honorer." : '')
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
