<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\VersionLogement;
use App\Domain\Comptes\Models\User;
use App\Support\Api\ErreurMetier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Modification d'un logement déjà PUBLIÉ (CdC § 7.1) : « toute modification ... d'un logement
 * publié repasse par la validation, la version publiée restant en ligne entre-temps ». Ne
 * couvre QUE les champs descriptifs (nom, description, capacité, règles...) — le prix
 * propriétaire a sa négociation (PrixDeLogement), le prix de vente sa double validation
 * (PrixDeLogement/DoubleValidation), les photos leur propre circuit d'acceptation
 * (PhotosDeLogement) : rien de tout cela ne passe par ici.
 *
 * Un logement qui n'est PAS publié (brouillon, refusé, prix à négocier, suspendu) n'a rien à
 * protéger — il n'est visible de personne d'autre que le propriétaire et l'administration — et
 * se modifie donc directement, sans version.
 */
final class VersionsDeLogement
{
    public function __construct(private readonly JournalAudit $journal) {}

    /** @param  array<string, mixed>  $donnees  sous-ensemble des champs modifiables du logement */
    public function proposer(Logement $logement, array $donnees, User $auteur): VersionLogement
    {
        $enAttente = $this->enAttente($logement);
        if ($enAttente !== null) {
            throw new ErreurMetier(
                'Une modification de ce logement attend déjà sa validation. Faites-la valider ou refuser d’abord.',
                'version_deja_en_attente',
            );
        }

        $version = VersionLogement::create(['logement_id' => $logement->id, 'valeurs' => $donnees, 'propose_par' => $auteur->id]);

        $this->journal->consigner(
            'version_proposee',
            'Modification proposée sur '.$logement->libelleAudit().' ('.implode(', ', array_keys($donnees)).'). En attente de validation.',
            $logement, auteur: $auteur,
        );

        return $version;
    }

    /** Un autre administrateur applique la version sur le logement, qui reste « publié » (CdC § 7.1). */
    public function valider(VersionLogement $version, User $administrateur): void
    {
        $this->exigerEnAttente($version);
        $this->exigerUnAdministrateur($administrateur);

        if ($administrateur->id === $version->propose_par && $version->auteur->profil->estPersonnel()) {
            throw new ErreurMetier(
                'Vous avez proposé cette modification : un autre administrateur doit la valider.',
                'validation_de_sa_propre_saisie',
                403,
            );
        }

        DB::transaction(function () use ($version, $administrateur): void {
            $logement = $version->logement;
            // `equipements` n'est pas une colonne : c'est une table pivot, synchronisée à part.
            $champs = Arr::except($version->valeurs, 'equipements');
            $avant = Arr::only($logement->getAttributes(), array_keys($champs));

            $logement->fill($champs)->save();
            if (array_key_exists('equipements', $version->valeurs)) {
                $logement->equipements()->sync($version->valeurs['equipements']);
            }
            $version->update(['statut' => 'validee', 'decide_par' => $administrateur->id, 'decide_le' => now()]);

            $this->journal->consigner(
                'version_validee', 'Validation de la modification de '.$logement->libelleAudit().'.',
                $logement, $avant, $champs, $administrateur,
            );
        });
    }

    public function refuser(VersionLogement $version, User $administrateur, string $motif): void
    {
        $this->exigerEnAttente($version);
        $this->exigerUnAdministrateur($administrateur);

        $version->update(['statut' => 'refusee', 'decide_par' => $administrateur->id, 'decide_le' => now(), 'motif_refus' => $motif]);
        $this->journal->consigner('version_refusee', 'Refus de la modification de '.$version->logement->libelleAudit()." : {$motif}", $version->logement, auteur: $administrateur);
    }

    public function annuler(VersionLogement $version, User $auteur): void
    {
        $this->exigerEnAttente($version);
        if ($version->propose_par !== $auteur->id) {
            throw new ErreurMetier('Seul l’auteur de la proposition peut l’annuler.', 'annulation_impossible', 403);
        }

        $version->update(['statut' => 'annulee', 'decide_par' => $auteur->id, 'decide_le' => now()]);
    }

    public function enAttente(Logement $logement): ?VersionLogement
    {
        return VersionLogement::query()->where('logement_id', $logement->id)->where('statut', 'en_attente')->first();
    }

    public function logementPublie(Logement $logement): bool
    {
        return $logement->etat_publication === EtatPublication::Publie;
    }

    private function exigerEnAttente(VersionLogement $version): void
    {
        if (! $version->enAttente()) {
            throw new ErreurMetier('Cette version a déjà été traitée.', 'version_deja_traitee');
        }
    }

    private function exigerUnAdministrateur(User $utilisateur): void
    {
        if (! $utilisateur->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }
    }
}
