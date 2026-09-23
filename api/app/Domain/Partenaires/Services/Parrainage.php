<?php

namespace App\Domain\Partenaires\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Partenaires\Models\CommissionApporteur;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;

/**
 * Parrainage par un apporteur d'affaires (CdC — apporteurs) :
 *
 *   « Parrainé par un code ; commission calculée sur chaque tranche encaissée d'un
 *     séjour, jamais sur le total ni sur la caution. »
 *
 * Une commission naît à CHAQUE règlement encaissé d'un séjour d'un client parrainé —
 * jamais sur un décaissement, jamais deux fois pour le même règlement (idempotence sur
 * `reglement_id`). Le reversement à l'apporteur n'a pas de service à lui : il réutilise
 * App\Domain\Caisse\Services\Caisse::saisirUnDecaissement, comme pour un propriétaire.
 */
final class Parrainage
{
    /** Rattache un client tout juste inscrit à l'apporteur qui l'a parrainé. */
    public function inscrireAvecCode(User $client, string $code): void
    {
        $apporteur = Apporteur::query()->where('code', $code)->first();

        if ($apporteur === null || ! $apporteur->actif) {
            throw new ErreurMetier('Ce code de parrainage est inconnu ou n’est plus actif.', 'code_parrain_invalide', 422);
        }

        $client->forceFill(['parraine_par_id' => $apporteur->id])->save();
    }

    /**
     * Un règlement vient d'être EFFECTUÉ (App\Domain\Caisse\Events\ReglementEffectue) : si
     * c'est un encaissement sur le séjour d'un client parrainé, l'apporteur gagne SA part
     * de CETTE tranche — jamais du total du séjour, jamais de la caution (qui n'a encore
     * aucun guichet et n'est donc jamais imputée à un séjour ici).
     */
    public function attribuerLaCommission(Reglement $reglement): void
    {
        if (! $reglement->estUnEncaissement()) {
            return;
        }

        if (CommissionApporteur::query()->where('reglement_id', $reglement->id)->exists()) {
            return; // idempotence : un même règlement ne crédite jamais deux fois.
        }

        $sejourId = $reglement->imputations()->where('affaire_type', (new Sejour)->getMorphClass())->value('affaire_id');
        if ($sejourId === null) {
            return;
        }

        $sejour = Sejour::query()->find($sejourId);
        $client = $sejour?->client;
        if ($client === null || $client->parraine_par_id === null) {
            return;
        }

        $apporteur = Apporteur::query()->find($client->parraine_par_id);
        if ($apporteur === null || ! $apporteur->actif) {
            return;
        }

        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id,
            'sejour_id' => $sejour->id,
            'reglement_id' => $reglement->id,
            'montant' => (int) round($reglement->montant * (float) $apporteur->pourcentage / 100),
        ]);
    }

    /** Ce qui reste dû à l'apporteur : ses commissions cumulées, moins ce qu'on lui a déjà reversé. */
    public function soldeDu(Apporteur $apporteur): int
    {
        $commissions = (int) CommissionApporteur::query()->where('apporteur_id', $apporteur->id)->sum('montant');

        $verse = (int) Reglement::query()
            ->where('tiers_id', $apporteur->user_id)
            ->where('sens', 'decaissement')
            ->where('etat', EtatDuReglement::Effectue)
            ->sum('montant');

        return max(0, $commissions - $verse);
    }
}
