<?php

namespace App\Domain\Transferts\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Enums\EtatDuTransfert;
use App\Domain\Transferts\Models\BaremeTransfert;
use App\Domain\Transferts\Models\Chauffeur;
use App\Domain\Transferts\Models\Transfert;
use App\Domain\Transferts\Models\Vehicule;
use App\Mail\TransfertAffecteMail;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Transfert et accompagnement, cas « pendant le séjour » (CdC § 6.6) : le client demande,
 * la réception affecte un chauffeur et un véhicule (et émet le code de prise en charge, même
 * mécanisme que le code d'arrivée), le chauffeur clôture avec le code que le client lui remet.
 *
 * Rémunération : le CdC ne donne aucune formule (contrairement à l'apporteur) — le montant
 * versé au chauffeur est saisi MANUELLEMENT par le gestionnaire au moment de l'affectation.
 */
final class GestionDesTransferts
{
    public const CODE_PRISE_EN_CHARGE = 'prise_en_charge';

    public function __construct(private readonly CodesSecrets $codes) {}

    /**
     * @param  array{lieu_de_prise_en_charge: string, commune_id: int, type_vehicule_souhaite_id: int, date_heure_prevue: string, nombre_passagers: int, nombre_bagages?: int, notes?: string|null}  $donnees
     */
    public function demander(Sejour $sejour, array $donnees): Transfert
    {
        $bareme = BaremeTransfert::query()
            ->where('commune_id', $donnees['commune_id'])
            ->where('type_vehicule_id', $donnees['type_vehicule_souhaite_id'])
            ->first();

        if ($bareme === null) {
            throw new ErreurMetier(
                'Aucun barème ne correspond à cette zone et à ce type de véhicule. Contactez la réception.',
                'bareme_introuvable',
                422,
            );
        }

        return Transfert::create([
            'sejour_id' => $sejour->id,
            'lieu_de_prise_en_charge' => $donnees['lieu_de_prise_en_charge'],
            'commune_id' => $donnees['commune_id'],
            'type_vehicule_souhaite_id' => $donnees['type_vehicule_souhaite_id'],
            'date_heure_prevue' => $donnees['date_heure_prevue'],
            'nombre_passagers' => $donnees['nombre_passagers'],
            'nombre_bagages' => $donnees['nombre_bagages'] ?? 0,
            'notes' => $donnees['notes'] ?? null,
            'montant' => $bareme->prix,
            'etat' => EtatDuTransfert::Demande,
        ]);
    }

    /** Réservé à la gestion quotidienne (mêmes profils que réservations/planning). */
    public function affecter(Transfert $transfert, Chauffeur $chauffeur, Vehicule $vehicule, int $montantVerseAuChauffeur): Transfert
    {
        if ($transfert->etat !== EtatDuTransfert::Demande) {
            throw new ErreurMetier('Ce transfert est déjà « '.$transfert->etat->libelle().' ».', 'affectation_impossible', 422);
        }
        if (! $chauffeur->actif) {
            throw new ErreurMetier('Ce chauffeur n’est pas actif.', 'chauffeur_inactif', 422);
        }
        if ($vehicule->chauffeur_id !== $chauffeur->id) {
            throw new ErreurMetier('Ce véhicule n’appartient pas à ce chauffeur.', 'vehicule_invalide', 422);
        }
        if (! $vehicule->actif) {
            throw new ErreurMetier('Ce véhicule n’est pas actif.', 'vehicule_inactif', 422);
        }
        if ($montantVerseAuChauffeur < 0) {
            throw new ErreurMetier('Le montant versé au chauffeur ne peut pas être négatif.', 'montant_invalide', 422);
        }

        $code = DB::transaction(function () use ($transfert, $chauffeur, $vehicule, $montantVerseAuChauffeur): string {
            $transfert->update([
                'etat' => EtatDuTransfert::Affecte,
                'chauffeur_id' => $chauffeur->id,
                'vehicule_id' => $vehicule->id,
                'montant_verse_au_chauffeur' => $montantVerseAuChauffeur,
            ]);

            return $this->codes->generer($transfert, self::CODE_PRISE_EN_CHARGE);
        });

        // Après la transaction : un envoi manqué ne défait JAMAIS l'affectation (CdC § 13.2).
        $transfert->load('sejour.client');
        if ($transfert->sejour->client !== null) {
            $this->envoyer($transfert->sejour->client->email, $transfert, $code);
            $this->codes->noterLEnvoi($transfert, self::CODE_PRISE_EN_CHARGE);
        }

        return $transfert->refresh();
    }

    /** Le chauffeur SAISIT le code que le client lui remet — il ne le lit jamais (CdC § 11). */
    public function cloturerParCode(Transfert $transfert, string $codeSaisi, User $chauffeurConnecte): Transfert
    {
        $chauffeur = Chauffeur::query()->where('user_id', $chauffeurConnecte->id)->first();
        if ($chauffeur === null || $transfert->chauffeur_id !== $chauffeur->id) {
            throw new ErreurMetier('Ce transfert ne vous est pas affecté.', 'transfert_non_affecte', 403);
        }
        if ($transfert->etat !== EtatDuTransfert::Affecte) {
            throw new ErreurMetier('Ce transfert est déjà « '.$transfert->etat->libelle().' ».', 'cloture_impossible', 422);
        }

        $this->codes->verifier($transfert, self::CODE_PRISE_EN_CHARGE, $codeSaisi, $chauffeurConnecte);

        $transfert->update(['etat' => EtatDuTransfert::Termine]);

        return $transfert->refresh();
    }

    /** Réservé à la gestion quotidienne. */
    public function annuler(Transfert $transfert): Transfert
    {
        if (in_array($transfert->etat, [EtatDuTransfert::Termine, EtatDuTransfert::Annule], true)) {
            throw new ErreurMetier('Ce transfert est déjà « '.$transfert->etat->libelle().' ».', 'annulation_impossible', 422);
        }

        $transfert->update(['etat' => EtatDuTransfert::Annule]);

        return $transfert->refresh();
    }

    /**
     * Total déjà gagné (transferts terminés) et ce qu'il reste à verser (gagné − déjà décaissé),
     * même logique que Parrainage::soldeDu pour l'apporteur — mais ici un total déjà gagné,
     * pas des commissions à calculer.
     *
     * @return array{total_gagne: int, solde_du: int}
     */
    public function mesGains(Chauffeur $chauffeur): array
    {
        $gagne = (int) Transfert::query()
            ->where('chauffeur_id', $chauffeur->id)
            ->where('etat', EtatDuTransfert::Termine)
            ->sum('montant_verse_au_chauffeur');

        $verse = (int) Reglement::query()
            ->where('tiers_id', $chauffeur->user_id)
            ->where('sens', 'decaissement')
            ->where('etat', EtatDuReglement::Effectue)
            ->sum('montant');

        return ['total_gagne' => $gagne, 'solde_du' => max(0, $gagne - $verse)];
    }

    private function envoyer(string $adresse, Transfert $transfert, string $code): void
    {
        try {
            Mail::to($adresse)->send(new TransfertAffecteMail($transfert, $code));
        } catch (Throwable $e) {
            Log::error('Envoi manqué (code de prise en charge)', ['erreur' => $e->getMessage()]);
        }
    }
}
