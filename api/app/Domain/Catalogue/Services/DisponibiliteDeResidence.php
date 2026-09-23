<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\FermetureResidence;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bouton « Occupée / Disponible » (CdC § 6.2 et § 10) : fermeture immédiate (occupée par le
 * propriétaire, louée hors plateforme, travaux), avertissement sur les séjours déjà confirmés
 * (qui restent honorés), date de réouverture prévue, historique journalisé — qui alimente le
 * taux de disponibilité. Utilisé aussi bien par l'administrateur (back office) que par le
 * propriétaire lui-même depuis son espace : même service, même règle.
 */
final class DisponibiliteDeResidence
{
    public function __construct(private readonly JournalAudit $journal) {}

    /** @return array{sejours_a_honorer: int} */
    public function basculer(Residence $residence, Disponibilite $cible, User $auteur, ?Carbon $reouverturePrevueLe, ?string $motif): array
    {
        if ($cible !== $residence->disponibilite) {
            DB::transaction(function () use ($residence, $cible, $reouverturePrevueLe, $motif, $auteur): void {
                $fermee = $cible === Disponibilite::Occupee;

                $fermee
                    ? FermetureResidence::create([
                        'residence_id' => $residence->id, 'fermee_le' => now(), 'fermee_par' => $auteur->id, 'motif' => $motif,
                        'reouverture_prevue_le' => $reouverturePrevueLe,
                    ])
                    : FermetureResidence::query()->where('residence_id', $residence->id)->whereNull('rouverte_le')
                        ->update(['rouverte_le' => now(), 'rouverte_par' => $auteur->id]);

                $residence->forceFill([
                    'disponibilite' => $cible,
                    'reouverture_prevue_le' => $fermee ? $reouverturePrevueLe : null,
                ])->saveQuietly();

                $this->journal->consigner(
                    $fermee ? 'residence_fermee' : 'residence_rouverte',
                    ($fermee ? 'Fermeture (« Occupée ») : ' : 'Réouverture (« Disponible ») : ').$residence->libelleAudit().'.',
                    $residence, auteur: $auteur,
                );
            });
        }

        return ['sejours_a_honorer' => $this->sejoursAHonorer($residence)];
    }

    /** À afficher AVANT de confirmer une fermeture (CdC § 6.2 : « averti avant de confirmer ») : ces séjours restent dus. */
    public function sejoursAHonorer(Residence $residence): int
    {
        return Sejour::query()
            ->whereIn('logement_id', $residence->logements()->pluck('id'))
            ->whereIn('etat', [EtatDuSejour::Confirme, EtatDuSejour::Arrive])
            ->where('depart', '>=', Carbon::today())->count();
    }

    /**
     * Taux de disponibilité sur la période : part du temps où la résidence n'était PAS fermée
     * « Occupée » (CdC § 6.2 : « compte dans le taux de disponibilité du propriétaire »).
     */
    public function tauxDeDisponibilite(Residence $residence, Carbon $depuis, Carbon $jusque): float
    {
        $joursTotal = max(1, $depuis->diffInDays($jusque));

        $fermetures = FermetureResidence::query()->where('residence_id', $residence->id)
            ->where('fermee_le', '<', $jusque)
            ->where(fn ($q) => $q->whereNull('rouverte_le')->orWhere('rouverte_le', '>', $depuis))
            ->get();

        $joursFermes = 0;
        foreach ($fermetures as $fermeture) {
            $debut = $fermeture->fermee_le->max($depuis);
            $fin = ($fermeture->rouverte_le ?? Carbon::now())->min($jusque);
            $joursFermes += max(0, (int) $debut->diffInDays($fin));
        }

        return round(max(0, min(1, 1 - ($joursFermes / $joursTotal))) * 100, 1);
    }

    /** @return list<array<string, mixed>> */
    public function historique(Residence $residence): array
    {
        return FermetureResidence::query()->where('residence_id', $residence->id)->orderByDesc('fermee_le')->get()
            ->map(fn (FermetureResidence $f): array => [
                'fermee_le' => $f->fermee_le->format('d/m/Y H:i'),
                'rouverte_le' => $f->rouverte_le?->format('d/m/Y H:i'),
                'reouverture_prevue_le' => $f->reouverture_prevue_le?->format('d/m/Y'),
                'motif' => $f->motif,
                'duree_jours' => $f->dureeEnJours(),
                'en_cours' => $f->enCours(),
            ])->all();
    }
}
