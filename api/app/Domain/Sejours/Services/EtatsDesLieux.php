<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Services\PiecesJustificatives;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Enums\TypeEtatDesLieux;
use App\Domain\Sejours\Models\EtatDesLieux;
use App\Domain\Sejours\Models\LigneEtatDesLieux;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use App\Support\Codes\Code128Svg;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * État des lieux d'entrée et de sortie (P2-SEJ-02, CdC § 6.3) : un inventaire de lignes, des
 * photos par ligne (mécanisme chiffré des pièces justificatives, réutilisé avec un type de
 * plus), une signature à l'écran. La comparaison d'une ligne de sortie avec son équivalent
 * d'entrée se fait par LIBELLÉ identique, en lecture seule (`ligneDEntreeCorrespondante`) —
 * jamais une clé étrangère entre deux lignes saisies à des moments différents.
 */
final class EtatsDesLieux
{
    public function __construct(private readonly PiecesJustificatives $pieces) {}

    /** Un état des lieux d'ENTRÉE se fait au check-in ou juste après ; celui de SORTIE, au check-out ou juste avant. */
    public function etablir(Sejour $sejour, TypeEtatDesLieux $type, User $auteur, ?string $commentaireGeneral = null): EtatDesLieux
    {
        if ($type === TypeEtatDesLieux::Entree && ! in_array($sejour->etat, [EtatDuSejour::Confirme, EtatDuSejour::Arrive], true)) {
            throw new ErreurMetier('L’état des lieux d’entrée se fait au moment du check-in.', 'etat_des_lieux_impossible', 422);
        }
        if ($type === TypeEtatDesLieux::Sortie && ! in_array($sejour->etat, [EtatDuSejour::Arrive, EtatDuSejour::Parti], true)) {
            throw new ErreurMetier('L’état des lieux de sortie se fait au moment du check-out.', 'etat_des_lieux_impossible', 422);
        }
        if ($sejour->etatsDesLieux()->where('type', $type->value)->exists()) {
            throw new ErreurMetier('Un état des lieux « '.$type->libelle().' » existe déjà pour ce séjour.', 'etat_des_lieux_deja_etabli', 422);
        }

        return EtatDesLieux::create([
            'sejour_id' => $sejour->id, 'type' => $type->value, 'commentaire_general' => $commentaireGeneral, 'etabli_par' => $auteur->id,
        ])->refresh();
    }

    /** @param  array{libelle: string, observation?: string|null}  $donnees */
    public function ajouterUneLigne(EtatDesLieux $etat, array $donnees): LigneEtatDesLieux
    {
        $this->refuserSiSigne($etat);

        $ordre = (int) $etat->lignes()->max('ordre') + 1;

        return LigneEtatDesLieux::create([
            'etat_des_lieux_id' => $etat->id, 'libelle' => trim($donnees['libelle']),
            'observation' => $donnees['observation'] ?? null, 'ordre' => $ordre,
        ])->refresh();
    }

    public function ajouterUnePhoto(LigneEtatDesLieux $ligne, UploadedFile $fichier, User $auteur): void
    {
        $this->refuserSiSigne($ligne->etatDesLieux);

        $this->pieces->deposer($ligne, TypeDePiece::PhotoEtatDesLieux, $fichier, null, $auteur);
    }

    /** Signature à l'écran (image encodée en base64) : verrouille l'état des lieux, il ne se modifie plus après. */
    public function signer(EtatDesLieux $etat, string $signatureBase64, User $auteur): EtatDesLieux
    {
        $this->refuserSiSigne($etat);
        if ($etat->lignes()->count() === 0) {
            throw new ErreurMetier('Un état des lieux se signe avec au moins une ligne.', 'lignes_manquantes', 422);
        }

        DB::transaction(fn () => $etat->update(['signature' => $signatureBase64, 'signe_le' => now()]));

        return $etat->refresh();
    }

    /**
     * Ligne d'ENTRÉE de même libellé, pour la comparaison à la sortie — en lecture seule,
     * jamais une relation stockée : deux agents peuvent saisir les libellés dans un ordre
     * différent, seul le texte les rapproche.
     */
    public function ligneDEntreeCorrespondante(Sejour $sejour, LigneEtatDesLieux $ligneDeSortie): ?LigneEtatDesLieux
    {
        $entree = $sejour->etatsDesLieux()->where('type', TypeEtatDesLieux::Entree->value)->first();
        if (! $entree instanceof EtatDesLieux) {
            return null;
        }

        $ligne = $entree->lignes()->whereRaw('lower(libelle) = ?', [mb_strtolower($ligneDeSortie->libelle)])->first();

        return $ligne instanceof LigneEtatDesLieux ? $ligne : null;
    }

    /**
     * PDF une page, comparaison entrée / sortie, code-barres Code 128 de la référence du
     * séjour (P2-SEJ-02) — jamais régénéré à l'identique en base : léger, il se recompose à la volée.
     */
    public function pdf(Sejour $sejour): string
    {
        $sejour->loadMissing(['logement.residence', 'client', 'etatsDesLieux.lignes', 'etatsDesLieux.etablisseur']);

        $entreeBrute = $sejour->etatsDesLieux->firstWhere('type', TypeEtatDesLieux::Entree);
        $entree = $entreeBrute instanceof EtatDesLieux ? $entreeBrute : null;
        $sortieBrute = $sejour->etatsDesLieux->firstWhere('type', TypeEtatDesLieux::Sortie);
        $sortie = $sortieBrute instanceof EtatDesLieux ? $sortieBrute : null;

        $lignes = collect($entree ? $entree->lignes : [])->keyBy(fn (LigneEtatDesLieux $l) => mb_strtolower($l->libelle));
        $comparaison = collect($sortie ? $sortie->lignes : [])->map(function (LigneEtatDesLieux $l) use ($lignes): array {
            $correspondante = $lignes->get(mb_strtolower($l->libelle));

            return [
                'libelle' => $l->libelle,
                'entree' => $correspondante?->observation,
                'sortie' => $l->observation,
                'ecart' => $correspondante !== null && $correspondante->observation !== $l->observation,
            ];
        });

        return Pdf::loadView('pdf.etat-des-lieux', [
            'sejour' => $sejour, 'entree' => $entree, 'sortie' => $sortie, 'comparaison' => $comparaison,
            'codeBarres' => Code128Svg::svg($sejour->reference),
        ])->setPaper('a4')->output();
    }

    private function refuserSiSigne(EtatDesLieux $etat): void
    {
        if ($etat->estSigne()) {
            throw new ErreurMetier('Cet état des lieux est signé : il ne se modifie plus.', 'etat_des_lieux_signe', 422);
        }
    }
}
