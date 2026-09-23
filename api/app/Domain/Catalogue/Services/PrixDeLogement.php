<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\NegociationPrix;
use App\Domain\Comptes\Models\User;
use App\Domain\Validation\Models\ChangementAValider;
use App\Domain\Validation\Services\DoubleValidation;
use App\Support\Api\ErreurMetier;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Les deux prix d'un logement (CdC § 7.2) :
 *
 *   prix propriétaire — proposé par le propriétaire, négocié, puis ARRÊTÉ ; c'est ce qui lui
 *                       sera reversé par nuitée consommée. Jamais montré au client.
 *   prix de vente     — fixé par l'administrateur ; seul prix affiché au client. Tout changement
 *                       passe par la double validation.
 *
 *   marge par nuitée = prix de vente − prix propriétaire
 */
final class PrixDeLogement
{
    public const CHAMP_PRIX_DE_VENTE = 'prix_vente';

    public function __construct(
        private readonly DoubleValidation $doubleValidation,
        private readonly PourcentageEntreprise $pourcentageEntreprise,
        private readonly ControleDesTarifs $controleDesTarifs,
    ) {}

    /** Une étape de la discussion : elle s'ajoute à l'historique sans changer le prix en vigueur. */
    public function noterUneProposition(Logement $logement, int $montant, ?string $commentaire, User $auteur): NegociationPrix
    {
        return NegociationPrix::create([
            'logement_id' => $logement->id, 'auteur_id' => $auteur->id, 'partie' => $this->partieDe($auteur),
            'montant' => $montant, 'commentaire' => $commentaire, 'nature' => 'proposition',
        ]);
    }

    /** L'accord est trouvé : le montant devient le prix propriétaire du logement. */
    public function arreterLePrixProprietaire(Logement $logement, int $montant, ?string $commentaire, User $auteur): NegociationPrix
    {
        if ($logement->prix_vente !== null && $montant > $logement->prix_vente) {
            throw new ErreurMetier(
                'Ce prix propriétaire dépasse le prix de vente en vigueur : chaque nuitée serait vendue à perte. Faites d’abord valider un nouveau prix de vente.',
                'vente_a_perte',
                422,
            );
        }

        return DB::transaction(function () use ($logement, $montant, $commentaire, $auteur): NegociationPrix {
            $accord = NegociationPrix::create([
                'logement_id' => $logement->id, 'auteur_id' => $auteur->id, 'partie' => $this->partieDe($auteur),
                'montant' => $montant, 'commentaire' => $commentaire, 'nature' => 'accord',
            ]);
            $logement->forceFill(['prix_proprietaire' => $montant])->save();

            return $accord;
        });
    }

    public function proposerLePrixDeVente(Logement $logement, int $montant, ?string $motif, User $administrateur): ChangementAValider
    {
        return $this->doubleValidation->proposer(
            $logement, self::CHAMP_PRIX_DE_VENTE, $montant, $administrateur, $motif, $this->controleDuPrixDeVente($logement),
        );
    }

    /**
     * Aucun séjour ne se vend à perte (CdC § 9.3, contrôle de cohérence) : le prix de vente ne
     * descend pas sous le prix propriétaire. Revérifié à la validation, car le prix propriétaire
     * a pu être renégocié entre la proposition et la validation.
     *
     * @return Closure(mixed, mixed): void
     */
    public function controleDuPrixDeVente(Logement $logement): Closure
    {
        return function (mixed $actuel, mixed $propose) use ($logement): void {
            $prixProprietaire = $logement->refresh()->prix_proprietaire;

            if ($prixProprietaire !== null && (int) $propose < $prixProprietaire) {
                throw new ErreurMetier(
                    'Ce prix de vente est inférieur au prix propriétaire : chaque nuitée serait vendue à perte.',
                    'vente_a_perte',
                    422,
                );
            }
        };
    }

    /**
     * Situation des prix, pour l'écran du back office.
     *
     * @return array<string, mixed>
     */
    public function situation(Logement $logement): array
    {
        $taux = $this->pourcentageEntreprise->tauxApplicable($logement);
        $conseille = $logement->prix_proprietaire === null ? null : (int) ceil($logement->prix_proprietaire * (1 + $taux / 100));

        return [
            'prix_proprietaire' => $logement->prix_proprietaire,
            'prix_vente' => $logement->prix_vente,
            'marge_par_nuitee' => $logement->prix_vente !== null && $logement->prix_proprietaire !== null
                ? $logement->prix_vente - $logement->prix_proprietaire
                : null,
            // Le pourcentage entreprise est un PLANCHER DE MARGE CONSEILLÉ, pas une formule imposée (CdC § 7.2).
            'pourcentage_entreprise' => $taux,
            'pourcentage_entreprise_derogation' => $logement->pourcentage_entreprise_derogation,
            'prix_de_vente_conseille' => $conseille,
            'marge_conseillee_respectee' => $conseille === null || $logement->prix_vente === null ? null : $logement->prix_vente >= $conseille,
            // Signal, jamais un blocage : la médiane des autres logements du même type (CdC § 7.3).
            'controle_mediane' => $this->controleDesTarifs->ecart($logement),
        ];
    }

    private function partieDe(User $auteur): string
    {
        return $auteur->profil->estPersonnel() ? 'administration' : 'proprietaire';
    }
}
