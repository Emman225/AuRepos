<?php

namespace App\Domain\Caisse\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Events\ReglementEffectue;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La caisse et son circuit de preuve (CdC § 8.2) — le SEUL endroit où un règlement avance.
 * « Le serveur refuse tout écart à cette règle. »
 *
 * Repris de Mon Gravier (`Traits/DoubleValidationPaiement.php`, `CircuitPreuveReglement.php`),
 * avec les règles de personnes doublées par des contraintes de base.
 */
final class Caisse
{
    public function __construct(
        private readonly SoldeDesSejours $soldes,
        private readonly Parametres $parametres,
        private readonly JournalAudit $journal,
        private readonly Recus $recus,
        private readonly Avances $avances,
    ) {}

    /**
     * Étape 1 — SAISIE d'un encaissement au guichet.
     *
     * @param  list<int>  $sejourIds  affaires cochées : toutes du MÊME client
     */
    public function saisirUnEncaissement(User $caissier, User $client, array $sejourIds, int $montant, ModeDeReglement $mode, string $notes, Guichet $guichet = Guichet::Sejours, ?string $referenceDuMode = null, bool $surplusEnAvance = false): Reglement
    {
        $this->exigerUnCaissier($caissier);
        $this->exigerUnModeActif($mode);

        return DB::transaction(function () use ($caissier, $client, $sejourIds, $montant, $mode, $notes, $guichet, $referenceDuMode, $surplusEnAvance): Reglement {
            // Verrou sur les séjours : deux caissiers ne peuvent pas encaisser le même reste dû en même temps.
            $sejours = Sejour::query()->whereKey($sejourIds)->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();

            if ($sejours->count() !== count(array_unique($sejourIds)) || $sejours->isEmpty()) {
                throw new ErreurMetier('Une des affaires cochées est introuvable.', 'affaire_introuvable', 422);
            }
            // Deux clients ne se mélangent jamais dans un même règlement (CdC § 8.1).
            if ($sejours->contains(fn (Sejour $s) => $s->client_id !== $client->id)) {
                throw new ErreurMetier('Toutes les affaires d’un règlement doivent appartenir au même client.', 'clients_melanges', 422);
            }
            if ($sejours->contains(fn (Sejour $s) => in_array($s->etat, [EtatDuSejour::Annule, EtatDuSejour::NoShow], true))) {
                throw new ErreurMetier('On n’encaisse pas sur un séjour annulé ou en no-show.', 'affaire_morte', 422);
            }

            // Imputation de la plus ancienne à la plus récente.
            $reste = $montant;
            $imputations = [];
            foreach ($sejours as $sejour) {
                $du = $this->soldes->de($sejour)['reste_du'];
                $part = min($reste, $du);
                if ($part > 0) {
                    $imputations[] = ['affaire_type' => $sejour->getMorphClass(), 'affaire_id' => $sejour->id, 'montant' => $part];
                    $reste -= $part;
                }
            }

            // « Un montant supérieur au reste dû propose “Enregistrer le surplus comme avance” » (CdC § 8.2).
            if ($reste > 0 && ! $surplusEnAvance) {
                throw new ErreurMetier(
                    'Ce montant dépasse de '.number_format($reste, 0, ',', ' ').' F le reste dû, qui tient compte des règlements déjà saisis, même non validés. Vous pouvez enregistrer ce surplus comme avance.',
                    'montant_superieur_au_reste_du',
                    422,
                );
            }

            $reglement = Reglement::create([
                'sens' => 'encaissement', 'guichet' => $guichet, 'agence_id' => $caissier->agence_id, 'tiers_id' => $client->id,
                'montant' => $montant, 'mode' => $mode, 'reference_du_mode' => $referenceDuMode, 'notes' => trim($notes),
                'saisi_par' => $caissier->id, 'saisi_le' => now(), 'surplus_en_avance' => $reste > 0,
            ]);
            $reglement->imputations()->createMany($imputations);

            $this->tracer($reglement, 'reglement_saisi', 'Saisie', $caissier);

            return $reglement->refresh();
        });
    }

    /**
     * Étape 1 — SAISIE d'un dépôt d'avance : une somme sans réservation en face (CdC § 4).
     * Elle ne devient une avance utilisable qu'une fois le circuit de preuve terminé.
     */
    public function saisirUnDepotDAvance(User $caissier, User $client, int $montant, ModeDeReglement $mode, string $notes, ?string $referenceDuMode = null): Reglement
    {
        $this->exigerUnCaissier($caissier);
        $this->exigerUnModeActif($mode);

        return DB::transaction(function () use ($caissier, $client, $montant, $mode, $notes, $referenceDuMode): Reglement {
            $reglement = Reglement::create([
                'sens' => 'encaissement', 'guichet' => Guichet::Avances, 'agence_id' => $caissier->agence_id, 'tiers_id' => $client->id,
                'montant' => $montant, 'mode' => $mode, 'reference_du_mode' => $referenceDuMode, 'notes' => trim($notes),
                'saisi_par' => $caissier->id, 'saisi_le' => now(),
            ]);
            $this->tracer($reglement, 'reglement_saisi', 'Saisie d’un dépôt d’avance', $caissier);

            return $reglement->refresh();
        });
    }

    /** Étape 1 — SAISIE d'un décaissement : reversement, remboursement, restitution (CdC § 4). */
    public function saisirUnDecaissement(User $auteur, User $beneficiaire, int $montant, ModeDeReglement $mode, string $notes, Guichet $guichet = Guichet::DettesPartenaires, ?string $referenceDuMode = null): Reglement
    {
        $this->exigerUnAdministrateur($auteur);
        $this->exigerUnCaissier($auteur);
        $this->exigerUnModeActif($mode);

        return DB::transaction(function () use ($auteur, $beneficiaire, $montant, $mode, $notes, $guichet, $referenceDuMode): Reglement {
            $reglement = Reglement::create([
                'sens' => 'decaissement', 'guichet' => $guichet, 'agence_id' => $auteur->agence_id, 'tiers_id' => $beneficiaire->id,
                'montant' => $montant, 'mode' => $mode, 'reference_du_mode' => $referenceDuMode, 'notes' => trim($notes),
                'saisi_par' => $auteur->id, 'saisi_le' => now(),
            ]);
            $this->tracer($reglement, 'reglement_saisi', 'Saisie', $auteur);

            return $reglement->refresh();
        });
    }

    /** Étape 2 — VALIDATION par un second administrateur, différent de l'auteur. */
    public function valider(Reglement $reglement, User $administrateur): void
    {
        $this->exigerUnAdministrateur($administrateur);
        $this->exigerEtat($reglement, EtatDuReglement::EnAttente);

        if ($administrateur->id === $reglement->saisi_par) {
            throw $this->memePersonne('Vous avez saisi ce règlement : un autre administrateur doit le valider.');
        }

        $reglement->update(['etat' => EtatDuReglement::APayer, 'valide_par' => $administrateur->id, 'valide_le' => now()]);
        $this->tracer($reglement, 'reglement_valide', 'Validation', $administrateur);
    }

    /** Étape 3 — PREUVE jointe par un troisième administrateur, différent des deux premiers. */
    public function joindreLaPreuve(Reglement $reglement, User $administrateur, UploadedFile $justificatif): void
    {
        $this->exigerUnAdministrateur($administrateur);
        $this->exigerEtat($reglement, EtatDuReglement::APayer);

        if (in_array($administrateur->id, [$reglement->saisi_par, $reglement->valide_par], true)) {
            throw $this->memePersonne('Vous avez déjà saisi ou validé ce règlement : la preuve revient à un troisième administrateur.');
        }

        // Chiffrée comme les pièces justificatives : un relevé ou un reçu porte des données personnelles.
        $chemin = 'preuves/'.$reglement->id.'/'.Str::uuid().'.chiffre';
        Storage::disk('local')->put($chemin, Crypt::encryptString((string) file_get_contents($justificatif->getRealPath())));

        $reglement->update([
            'etat' => EtatDuReglement::PreuveJointe, 'preuve_par' => $administrateur->id, 'preuve_le' => now(),
            'preuve_chemin' => $chemin, 'preuve_nom' => mb_substr($justificatif->getClientOriginalName(), 0, 255), 'preuve_mime' => (string) $justificatif->getMimeType(),
        ]);
        $this->tracer($reglement, 'reglement_preuve_jointe', 'Preuve jointe', $administrateur);
    }

    /** Étape 4 — FINALISATION par le MÊME troisième administrateur. Alors seulement la somme compte. */
    public function finaliser(Reglement $reglement, User $administrateur): void
    {
        $this->exigerUnAdministrateur($administrateur);
        $this->exigerEtat($reglement, EtatDuReglement::PreuveJointe);

        if ($administrateur->id !== $reglement->preuve_par) {
            throw $this->memePersonne('Seul l’administrateur qui a joint la preuve finalise le règlement.');
        }

        DB::transaction(function () use ($reglement, $administrateur): void {
            $reglement->update(['etat' => EtatDuReglement::Effectue, 'finalise_par' => $administrateur->id, 'finalise_le' => now()]);
            // Dans la MÊME transaction : le numéro de reçu (sans trou) et l'avance éventuelle naissent avec l'état « effectué ».
            $this->recus->attribuerLeNumero($reglement);
            $this->avances->constituerDepuis($reglement);
            $this->tracer($reglement, 'reglement_effectue', 'Finalisation', $administrateur);
        });

        // Ce que la finalisation déclenche (reçu, confirmation du séjour, points…) s'y abonne sans toucher à la caisse.
        ReglementEffectue::dispatch($reglement->refresh());
    }

    /** Un règlement non effectué peut être rejeté : sa place réservée sur le reste dû est rendue. */
    public function rejeter(Reglement $reglement, User $administrateur, string $motif): void
    {
        $this->exigerUnAdministrateur($administrateur);

        if (! $reglement->etat->enCours()) {
            throw new ErreurMetier('Ce règlement est déjà '.mb_strtolower($reglement->etat->libelle()).' : il ne peut plus être rejeté.', 'reglement_deja_traite');
        }

        $reglement->update(['etat' => EtatDuReglement::Rejete, 'rejete_par' => $administrateur->id, 'rejete_le' => now(), 'motif_rejet' => $motif]);
        $this->tracer($reglement, 'reglement_rejete', "Rejet ({$motif})", $administrateur);
    }

    public function contenuDeLaPreuve(Reglement $reglement): string
    {
        return Crypt::decryptString((string) Storage::disk('local')->get((string) $reglement->preuve_chemin));
    }

    /** L'agence n'est jamais choisie : c'est celle du caissier. Sans agence, on n'encaisse pas (CdC § 8.1). */
    private function exigerUnCaissier(User $utilisateur): void
    {
        if (! $utilisateur->peutEncaisser()) {
            throw new ErreurMetier('Votre compte n’est rattaché à aucune agence : vous ne pouvez pas encaisser.', 'caissier_sans_agence', 403);
        }
    }

    private function exigerUnAdministrateur(User $utilisateur): void
    {
        if (! $utilisateur->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }
    }

    private function exigerUnModeActif(ModeDeReglement $mode): void
    {
        $parametre = $mode->parametre();
        if ($parametre !== null && ! $this->parametres->valeur($parametre)) {
            throw new ErreurMetier('Le mode de règlement « '.$mode->libelle().' » est désactivé dans les Paramètres.', 'mode_de_reglement_inactif', 422);
        }
    }

    private function exigerEtat(Reglement $reglement, EtatDuReglement $attendu): void
    {
        if ($reglement->etat !== $attendu) {
            throw new ErreurMetier(
                'Ce règlement est « '.$reglement->etat->libelle().' » : cette étape attend un règlement « '.$attendu->libelle().' ».',
                'etape_hors_sequence',
            );
        }
    }

    private function memePersonne(string $message): ErreurMetier
    {
        return new ErreurMetier($message, 'meme_personne_dans_le_circuit', 403);
    }

    private function tracer(Reglement $reglement, string $action, string $etape, User $auteur): void
    {
        $this->journal->consigner($action, "{$etape} : ".$reglement->libelleAudit().' — '.$reglement->etat->libelle().'.', $reglement,
            apres: ['etat' => $reglement->etat->value, 'montant' => $reglement->montant, 'mode' => $reglement->mode->value], auteur: $auteur);
    }
}
