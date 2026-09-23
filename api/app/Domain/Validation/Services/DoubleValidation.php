<?php

namespace App\Domain\Validation\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Comptes\Models\User;
use App\Domain\Validation\Models\ChangementAValider;
use App\Support\Api\ErreurMetier;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Double validation d'une valeur sensible (CdC § 7.2 et § 7.3) :
 * un administrateur PROPOSE, un AUTRE administrateur VALIDE. Entre les deux,
 * l'ancienne valeur reste en vigueur — rien ne change d'un seul geste.
 *
 * Même esprit que la double validation des encaissements de Mon Gravier
 * (`Traits/DoubleValidationPaiement.php`), étendu aux prix et aux pourcentages.
 */
final class DoubleValidation
{
    public function __construct(private readonly JournalAudit $journal) {}

    /**
     * @param  Closure(mixed $actuelle, mixed $proposee): void|null  $controle  règle métier à vérifier
     *                                                                          à la proposition ET à la validation
     */
    public function proposer(Model $sujet, string $champ, mixed $valeur, User $auteur, ?string $motif = null, ?Closure $controle = null): ChangementAValider
    {
        $this->exigerUnAdministrateur($auteur);
        $actuelle = $sujet->getAttribute($champ);

        if ($actuelle === $valeur) {
            throw new ErreurMetier('Cette valeur est déjà en vigueur.', 'valeur_inchangee', 422);
        }
        $controle?->__invoke($actuelle, $valeur);

        $dejaEnAttente = ChangementAValider::query()
            ->where('sujet_type', $sujet->getMorphClass())->where('sujet_id', $sujet->getKey())
            ->where('champ', $champ)->where('statut', 'en_attente')->exists();
        if ($dejaEnAttente) {
            throw new ErreurMetier(
                'Un changement de cette valeur attend déjà sa validation. Faites-le valider, refuser ou annuler d’abord.',
                'changement_deja_en_attente',
            );
        }

        $changement = ChangementAValider::create([
            'sujet_type' => $sujet->getMorphClass(), 'sujet_id' => $sujet->getKey(), 'champ' => $champ,
            'valeur_actuelle' => $actuelle, 'valeur_proposee' => $valeur, 'motif' => $motif, 'propose_par' => $auteur->id,
        ]);

        $this->journal->consigner('changement_propose', sprintf(
            'Proposition : %s de %s — %s → %s. En attente d’un second administrateur.',
            $champ, JournalAudit::libelleDe($sujet), self::lisible($actuelle), self::lisible($valeur),
        ), $sujet, [$champ => $actuelle], [$champ => $valeur], $auteur);

        return $changement;
    }

    /** @param Closure(mixed $actuelle, mixed $proposee): void|null $controle */
    public function valider(ChangementAValider $changement, User $validateur, ?Closure $controle = null): void
    {
        $this->exigerUnAdministrateur($validateur);
        $this->exigerEnAttente($changement);

        if ($changement->propose_par === $validateur->id) {
            throw new ErreurMetier(
                'Vous avez proposé ce changement : un autre administrateur doit le valider.',
                'validation_de_sa_propre_saisie',
                403,
            );
        }

        DB::transaction(function () use ($changement, $validateur, $controle): void {
            $sujet = $changement->sujet ?? throw new ErreurMetier('L’élément concerné n’existe plus.', 'sujet_disparu', 410);
            // La situation a pu changer depuis la proposition : on revérifie au moment d'appliquer.
            $controle?->__invoke($sujet->getAttribute($changement->champ), $changement->valeur_proposee);

            // saveQuietly : la trace explicite ci-dessous nomme les DEUX personnes, ce que la trace automatique ne sait pas faire.
            $sujet->forceFill([$changement->champ => $changement->valeur_proposee])->saveQuietly();
            $changement->update(['statut' => 'valide', 'decide_par' => $validateur->id, 'decide_le' => now()]);

            $this->journal->consigner('changement_valide', sprintf(
                'Validation : %s de %s passe à %s (proposé par %s).',
                $changement->champ, JournalAudit::libelleDe($sujet), self::lisible($changement->valeur_proposee), $changement->auteur->nomComplet(),
            ), $sujet, [$changement->champ => $changement->valeur_actuelle], [$changement->champ => $changement->valeur_proposee], $validateur);
        });
    }

    public function refuser(ChangementAValider $changement, User $validateur, string $motif): void
    {
        $this->exigerUnAdministrateur($validateur);
        $this->exigerEnAttente($changement);

        $changement->update(['statut' => 'refuse', 'decide_par' => $validateur->id, 'decide_le' => now(), 'motif_decision' => $motif]);
        $this->journal->consigner('changement_refuse', "Refus du changement de {$changement->champ} : {$motif}", $changement->sujet, auteur: $validateur);
    }

    /** L'auteur retire sa propre proposition ; l'ancienne valeur n'a jamais cessé d'être en vigueur. */
    public function annuler(ChangementAValider $changement, User $auteur): void
    {
        $this->exigerEnAttente($changement);
        if ($changement->propose_par !== $auteur->id) {
            throw new AuthorizationException('Seul l’auteur d’une proposition peut l’annuler.');
        }

        $changement->update(['statut' => 'annule', 'decide_par' => $auteur->id, 'decide_le' => now()]);
        $this->journal->consigner('changement_annule', "Annulation de la proposition sur {$changement->champ}.", $changement->sujet, auteur: $auteur);
    }

    private function exigerUnAdministrateur(User $utilisateur): void
    {
        if (! $utilisateur->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }
    }

    private function exigerEnAttente(ChangementAValider $changement): void
    {
        if (! $changement->enAttente()) {
            throw new ErreurMetier('Ce changement a déjà été traité.', 'changement_deja_traite');
        }
    }

    private static function lisible(mixed $valeur): string
    {
        return match (true) {
            $valeur === null => 'aucune valeur',
            is_int($valeur) => number_format($valeur, 0, ',', ' '),
            is_scalar($valeur) => (string) $valeur,
            default => (string) json_encode($valeur, JSON_UNESCAPED_UNICODE),
        };
    }
}
