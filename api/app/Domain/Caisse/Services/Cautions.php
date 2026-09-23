<?php

namespace App\Domain\Caisse\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Models\RetenueDeCaution;
use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Services\Factures;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Services\PiecesJustificatives;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guichet Cautions (P2-CAU-01 à 03, CdC § 6.3 et § 8) : dépôt, restitution et retenue de la
 * caution d'UN séjour.
 *
 * Dépôt et restitution sont des RÈGLEMENTS ordinaires (`Reglement`, guichet `Cautions`) : ils
 * empruntent le MÊME circuit de preuve à quatre étapes que tout encaissement ou décaissement
 * (`Caisse::valider`, `joindreLaPreuve`, `finaliser`, `rejeter` — non dupliqués ici) et le même
 * mécanisme de reçu (`Recus::attribuerLeNumero`, préfixes RK / RK-R posés sur `Guichet::Cautions`).
 * Ils sont rattachés au séjour par `reglements.sejour_id`, JAMAIS par une imputation : la
 * caution n'entre jamais dans le reste dû du séjour (`SoldeDesSejours::de`).
 *
 * La retenue n'est PAS un mouvement de caisse : rien n'est décaissé sur la part retenue, elle
 * reste acquise à l'entreprise. Elle porte un motif obligatoire (jamais une formule de dégât
 * automatique — même principe que la caution retenue au check-out, CdC), des justificatifs
 * chiffrés (`PiecesJustificatives`) et fait naître une facture normalisée « frais de
 * dégradation / retard » (`Factures::genererFraisDeDegradation`).
 */
final class Cautions
{
    public function __construct(
        private readonly Caisse $caisse,
        private readonly Parametres $parametres,
        private readonly JournalAudit $journal,
        private readonly PiecesJustificatives $pieces,
        private readonly Factures $factures,
    ) {}

    /** Étape 1 — SAISIE du dépôt : encaissement de la caution d'UN séjour, montant exact. */
    public function deposer(User $caissier, Sejour $sejour, int $montant, ModeDeReglement $mode, string $notes, ?string $referenceDuMode = null): Reglement
    {
        $this->exigerUnCaissier($caissier);
        $this->exigerUnModeActif($mode);
        $this->exigerUneAffaireVivante($sejour);

        if ($sejour->caution <= 0) {
            throw new ErreurMetier('Ce séjour n’a pas de caution à encaisser.', 'caution_absente', 422);
        }
        if ($this->estEncaissee($sejour) || $this->depotEnCours($sejour)) {
            throw new ErreurMetier('La caution de ce séjour est déjà encaissée, ou en cours d’encaissement.', 'caution_deja_encaissee', 422);
        }
        if ($montant !== $sejour->caution) {
            throw new ErreurMetier('Le montant doit correspondre exactement à la caution du séjour ('.number_format($sejour->caution, 0, ',', ' ').' F).', 'montant_different_de_la_caution', 422);
        }

        return DB::transaction(function () use ($caissier, $sejour, $montant, $mode, $notes, $referenceDuMode): Reglement {
            $reglement = Reglement::create([
                'sens' => 'encaissement', 'guichet' => Guichet::Cautions, 'agence_id' => $caissier->agence_id,
                'tiers_id' => $sejour->client_id, 'sejour_id' => $sejour->id, 'montant' => $montant, 'mode' => $mode,
                'reference_du_mode' => $referenceDuMode, 'notes' => trim($notes),
                'saisi_par' => $caissier->id, 'saisi_le' => now(),
            ]);
            $this->journal->consigner('reglement_saisi', 'Saisie : dépôt de caution — '.$sejour->libelleAudit().'.', $reglement, auteur: $caissier);

            return $reglement->refresh();
        });
    }

    /**
     * Restitution (P2-CAU-01/03) : décaissement au client, via le circuit générique déjà en
     * place (`Caisse::saisirUnDecaissement`) — un administrateur-caissier, comme tout décaissement.
     */
    public function restituer(User $auteur, Sejour $sejour, ?int $montant, ModeDeReglement $mode, string $notes, ?string $referenceDuMode = null): Reglement
    {
        if (! $this->estEncaissee($sejour)) {
            throw new ErreurMetier('La caution de ce séjour n’est pas encaissée : rien à restituer.', 'caution_non_encaissee', 422);
        }

        $solde = $this->soldeDe($sejour);
        $montant ??= $solde['detenue'];
        if ($montant <= 0 || $montant > $solde['detenue']) {
            throw new ErreurMetier('La restitution ne peut pas dépasser la part encore détenue ('.number_format($solde['detenue'], 0, ',', ' ').' F).', 'restitution_superieure_au_detenu', 422);
        }

        return $this->caisse->saisirUnDecaissement($auteur, $sejour->client, $montant, $mode, $notes, Guichet::Cautions, $referenceDuMode, $sejour->id);
    }

    /**
     * Retenue (P2-CAU-01/02) : jamais une formule — montant et motif viennent tous deux de la
     * saisie — avec ses justificatifs et sa facture « frais de dégradation / retard ».
     *
     * @param  list<UploadedFile>  $justificatifs
     */
    public function retenir(User $administrateur, Sejour $sejour, int $montant, string $motif, array $justificatifs, User $auteur): RetenueDeCaution
    {
        $this->exigerUnAdministrateur($administrateur);

        if (! $this->estEncaissee($sejour)) {
            throw new ErreurMetier('La caution de ce séjour n’est pas encaissée : rien à retenir.', 'caution_non_encaissee', 422);
        }
        if (trim($motif) === '') {
            throw new ErreurMetier('Une retenue sur la caution doit toujours être motivée.', 'motif_obligatoire', 422);
        }

        $solde = $this->soldeDe($sejour);
        if ($montant <= 0 || $montant > $solde['detenue']) {
            throw new ErreurMetier('La retenue ne peut pas dépasser la part encore détenue ('.number_format($solde['detenue'], 0, ',', ' ').' F).', 'retenue_superieure_au_detenu', 422);
        }

        return DB::transaction(function () use ($sejour, $montant, $motif, $justificatifs, $auteur): RetenueDeCaution {
            $facture = $this->factures->genererFraisDeDegradation($sejour, $montant, $motif, $auteur);

            $retenue = RetenueDeCaution::create([
                'sejour_id' => $sejour->id, 'montant' => $montant, 'motif' => trim($motif),
                'facture_id' => $facture->id, 'effectuee_par' => $auteur->id, 'effectuee_le' => now(),
            ]);

            foreach ($justificatifs as $fichier) {
                $this->pieces->deposer($retenue, TypeDePiece::JustificatifCaution, $fichier, null, $auteur);
            }

            $this->journal->consigner(
                'caution_retenue',
                'Retenue de '.number_format($montant, 0, ',', ' ').' F sur la caution — '.$sejour->libelleAudit()." ({$motif}).",
                $retenue, auteur: $auteur,
            );

            return $retenue->refresh()->load('pieces');
        });
    }

    /** La caution est encaissée dès lors que son dépôt a passé le circuit de preuve en entier (CdC). */
    public function estEncaissee(Sejour $sejour): bool
    {
        if ($sejour->caution <= 0) {
            return true; // rien à encaisser : la condition ne peut pas bloquer ce qui n'existe pas
        }

        return $this->reglementsDuSejour($sejour, 'encaissement')->where('etat', EtatDuReglement::Effectue->value)->exists();
    }

    /** Dépôt saisi mais pas encore effectué : la caution RÉSERVE SA PLACE, on ne peut pas en déposer une seconde. */
    private function depotEnCours(Sejour $sejour): bool
    {
        return $this->reglementsDuSejour($sejour, 'encaissement')
            ->whereIn('etat', array_map(fn (EtatDuReglement $e) => $e->value, array_filter(EtatDuReglement::cases(), fn (EtatDuReglement $e) => $e->enCours())))
            ->exists();
    }

    /**
     * Où en est la caution d'un séjour : ce qui a été déposé, retenu, restitué, et ce qui reste détenu.
     *
     * @return array{totale: int, deposee: int, retenue: int, restituee: int, detenue: int}
     */
    public function soldeDe(Sejour $sejour): array
    {
        $deposee = (int) $this->reglementsDuSejour($sejour, 'encaissement')->where('etat', EtatDuReglement::Effectue->value)->sum('montant');
        $retenue = (int) RetenueDeCaution::query()->where('sejour_id', $sejour->id)->sum('montant');
        $restituee = (int) $this->reglementsDuSejour($sejour, 'decaissement')->where('etat', EtatDuReglement::Effectue->value)->sum('montant');

        return [
            'totale' => $sejour->caution,
            'deposee' => $deposee,
            'retenue' => $retenue,
            'restituee' => $restituee,
            'detenue' => max(0, $deposee - $retenue - $restituee),
        ];
    }

    /**
     * État des cautions (P2-CAU-03) : preuve de l'égalité retenue + restituée + détenue =
     * caution totale, sur les dépôts finalisés d'une période, optionnellement d'une résidence —
     * une simple agrégation, pas un moteur de rapport.
     *
     * @return array{periode: array{du: ?string, au: ?string}, residence_id: ?int, nombre_de_sejours: int, caution_totale: int, retenue: int, restituee: int, detenue: int, identite_verifiee: bool}
     */
    public function etatDesCautions(?int $residenceId, ?Carbon $du, ?Carbon $au): array
    {
        $sejourIds = Reglement::query()
            ->where('guichet', Guichet::Cautions->value)->where('sens', 'encaissement')->where('etat', EtatDuReglement::Effectue->value)
            ->whereNotNull('sejour_id')
            ->when($du, fn ($q) => $q->where('finalise_le', '>=', $du))
            ->when($au, fn ($q) => $q->where('finalise_le', '<=', $au))
            ->when($residenceId, fn ($q) => $q->whereHas('sejour.logement', fn ($q2) => $q2->where('residence_id', $residenceId)))
            ->pluck('sejour_id');

        $vide = [
            'periode' => ['du' => $du?->toDateString(), 'au' => $au?->toDateString()], 'residence_id' => $residenceId,
            'nombre_de_sejours' => 0, 'caution_totale' => 0, 'retenue' => 0, 'restituee' => 0, 'detenue' => 0, 'identite_verifiee' => true,
        ];
        if ($sejourIds->isEmpty()) {
            return $vide;
        }

        $cautionTotale = (int) Sejour::query()->whereIn('id', $sejourIds)->sum('caution');
        $retenue = (int) RetenueDeCaution::query()->whereIn('sejour_id', $sejourIds)->sum('montant');
        $restituee = (int) Reglement::query()
            ->where('guichet', Guichet::Cautions->value)->where('sens', 'decaissement')->where('etat', EtatDuReglement::Effectue->value)
            ->whereIn('sejour_id', $sejourIds)->sum('montant');
        $detenue = max(0, $cautionTotale - $retenue - $restituee);

        return [
            'periode' => $vide['periode'], 'residence_id' => $residenceId, 'nombre_de_sejours' => $sejourIds->count(),
            'caution_totale' => $cautionTotale, 'retenue' => $retenue, 'restituee' => $restituee, 'detenue' => $detenue,
            'identite_verifiee' => ($retenue + $restituee + $detenue) === $cautionTotale,
        ];
    }

    /** @return Builder<Reglement> */
    private function reglementsDuSejour(Sejour $sejour, string $sens): Builder
    {
        return Reglement::query()->where('sejour_id', $sejour->id)->where('guichet', Guichet::Cautions->value)->where('sens', $sens);
    }

    private function exigerUneAffaireVivante(Sejour $sejour): void
    {
        if (in_array($sejour->etat, [EtatDuSejour::Annule, EtatDuSejour::NoShow], true)) {
            throw new ErreurMetier('On n’encaisse pas de caution sur un séjour annulé ou en no-show.', 'affaire_morte', 422);
        }
    }

    /** L'agence n'est jamais choisie : c'est celle du caissier. Même règle que `Caisse::exigerUnCaissier`. */
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
}
