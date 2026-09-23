<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Support\Api\ErreurMetier;

/**
 * Validation d'un bon de mise à disposition par le propriétaire (CdC § 7.2 : « chaque séjour
 * confirmé génère un bon que le propriétaire voit ; sa validation vaut accord »). La création et
 * la validation AUTOMATIQUE selon le mandat sont déjà faites par
 * App\Domain\Sejours\Services\ConfirmationDeSejour::creerLeBon (P3-PRO-01) ; ceci couvre le cas
 * manuel restant : le propriétaire valide lui-même, depuis son espace.
 */
final class BonsDeMiseADisposition
{
    public function __construct(private readonly JournalAudit $journal) {}

    public function valider(BonDeMiseADisposition $bon, User $proprietaire): void
    {
        if ($bon->etat !== 'en_attente') {
            throw new ErreurMetier('Ce bon a déjà été '.($bon->etat === 'valide' ? 'validé' : 'annulé').'.', 'bon_deja_traite');
        }

        // saveQuietly : la trace explicite ci-dessous remplace la trace automatique d'EstAudite.
        $bon->forceFill(['etat' => 'valide', 'valide_le' => now(), 'valide_par' => $proprietaire->id])->saveQuietly();
        $this->journal->consigner('bon_valide', 'Validation par le propriétaire : '.$bon->libelleAudit().'.', $bon, auteur: $proprietaire);
    }
}
