<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Support\Api\ErreurMetier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Alignment;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

/**
 * Dépôt, recadrage, ordre, couverture et suppression des photos d'un logement.
 *
 * Ce que le public voit est TOUJOURS la version redimensionnée et filigranée ;
 * l'original reste sur le disque privé et sert de base à tout recadrage.
 */
final class PhotosDeLogement
{
    public const LARGEUR_AFFICHAGE = 1600;

    public const LARGEUR_VIGNETTE = 480;

    /** En dessous, la photo est trop petite pour une fiche logement. */
    public const LARGEUR_MINIMALE = 800;

    public const HAUTEUR_MINIMALE = 600;

    private const QUALITE_JPEG = 82;

    public function __construct(
        private readonly Parametres $parametres,
        private readonly JournalAudit $journal,
    ) {}

    public function ajouter(Logement $logement, UploadedFile $fichier, ?string $legende, User $auteur): PhotoLogement
    {
        $maximum = (int) $this->parametres->valeur('proprietaires.photos_maximum');
        if ($logement->photos()->count() >= $maximum) {
            throw new ErreurMetier("Ce logement a déjà {$maximum} photos, le maximum autorisé. Supprimez-en une avant d’en ajouter.", 'photos_maximum_atteint', 422);
        }

        $dossier = "logements/{$logement->id}";
        $nom = Str::uuid()->toString();
        $original = "{$dossier}/originaux/{$nom}.".strtolower($fichier->getClientOriginalExtension() ?: 'jpg');

        Storage::disk('local')->put($original, (string) file_get_contents($fichier->getRealPath()));

        $image = $this->gestionnaire()->decodePath(Storage::disk('local')->path($original))->orient();

        return DB::transaction(function () use ($logement, $fichier, $legende, $auteur, $dossier, $nom, $original, $image): PhotoLogement {
            $photo = new PhotoLogement([
                'logement_id' => $logement->id,
                'chemin_original' => $original,
                'chemin_affichage' => "{$dossier}/{$nom}.jpg",
                'chemin_vignette' => "{$dossier}/{$nom}-vignette.jpg",
                'legende' => $legende,
                'ordre' => (int) $logement->photos()->max('ordre') + 1,
                // La première photo déposée devient la couverture, jusqu'à ce qu'on en désigne une autre.
                'couverture' => ! $logement->photos()->where('couverture', true)->exists(),
                'largeur' => $image->width(),
                'hauteur' => $image->height(),
                'taille_octets' => (int) $fichier->getSize(),
                'ajoutee_par' => $auteur->id,
                'ajoutee_par_administration' => $auteur->profil->estPersonnel(),
                // Dépôt par l'administration : publié d'office. Dépôt par le propriétaire : à valider (P3-PUB-02).
                'etat' => $auteur->profil->estPersonnel() ? 'acceptee' : 'en_attente',
            ]);

            $this->produireLesVersions($photo, $image);
            $photo->save();

            return $photo;
        });
    }

    /** Recadre à partir de l'ORIGINAL (coordonnées en pixels de l'original), puis régénère les versions. */
    public function recadrer(PhotoLogement $photo, int $x, int $y, int $largeur, int $hauteur): PhotoLogement
    {
        $image = $this->gestionnaire()->decodePath(Storage::disk('local')->path($photo->chemin_original))->orient();

        if ($x + $largeur > $image->width() || $y + $hauteur > $image->height()) {
            throw new ErreurMetier('Le cadre dépasse les bords de la photo.', 'recadrage_hors_image', 422);
        }

        $image->crop($largeur, $hauteur, $x, $y);
        $this->produireLesVersions($photo, $image);

        $photo->forceFill(['largeur' => $largeur, 'hauteur' => $hauteur])->save();
        $photo->touch(); // change l'URL servie : le navigateur recharge l'image

        return $photo;
    }

    public function definirCouverture(PhotoLogement $photo): void
    {
        DB::transaction(function () use ($photo): void {
            // D'abord retirer l'ancienne : la base n'admet qu'une couverture par logement.
            PhotoLogement::where('logement_id', $photo->logement_id)->where('couverture', true)
                ->whereKeyNot($photo->id)->get()->each->update(['couverture' => false]);
            $photo->update(['couverture' => true]);
        });
    }

    /** @param list<int> $identifiants toutes les photos du logement, dans l'ordre voulu */
    public function reordonner(Logement $logement, array $identifiants): void
    {
        $existants = $logement->photos()->pluck('id')->all();
        if (count($identifiants) !== count($existants) || array_diff($existants, $identifiants) !== []) {
            throw new ErreurMetier('L’ordre doit citer une fois chaque photo du logement, et elles seules.', 'ordre_incomplet', 422);
        }

        DB::transaction(function () use ($identifiants): void {
            foreach ($identifiants as $position => $id) {
                PhotoLogement::whereKey($id)->update(['ordre' => $position + 1]);
            }
        });
    }

    /** Une suppression est journalisée avec son motif (CdC § 7.1). */
    public function supprimer(PhotoLogement $photo, string $motif, User $auteur): void
    {
        DB::transaction(function () use ($photo, $motif, $auteur): void {
            $etaitCouverture = $photo->couverture;
            $logementId = $photo->logement_id;
            $libelle = $photo->libelleAudit();

            $photo->deleteQuietly(); // la trace détaillée ci-dessous remplace la trace automatique
            $this->journal->consigner('suppression_photo', "Suppression : {$libelle}. Motif : {$motif}", $photo, [
                'legende' => $photo->legende, 'motif' => $motif, 'ajoutee_par_administration' => $photo->ajoutee_par_administration,
            ], auteur: $auteur);

            if ($etaitCouverture) {
                PhotoLogement::where('logement_id', $logementId)->orderBy('ordre')->first()?->update(['couverture' => true]);
            }
        });

        Storage::disk('local')->delete($photo->chemin_original);
        Storage::disk('public')->delete([$photo->chemin_affichage, $photo->chemin_vignette]);
    }

    private function produireLesVersions(PhotoLogement $photo, ImageInterface $image): void
    {
        $affichage = (clone $image)->scaleDown(width: self::LARGEUR_AFFICHAGE);
        $this->filigraner($affichage);
        Storage::disk('public')->put($photo->chemin_affichage, (string) $affichage->encode(new JpegEncoder(quality: self::QUALITE_JPEG)));

        $vignette = (clone $image)->scaleDown(width: self::LARGEUR_VIGNETTE);
        Storage::disk('public')->put($photo->chemin_vignette, (string) $vignette->encode(new JpegEncoder(quality: self::QUALITE_JPEG)));
    }

    /** Filigrane au nom de la plateforme, en bas à droite, lisible sur fond clair comme sur fond sombre. */
    private function filigraner(ImageInterface $image): void
    {
        $texte = (string) ($this->parametres->valeur('proprietaires.filigrane') ?: $this->parametres->valeur('general.nom_plateforme'));
        if ($texte === '') {
            return;
        }

        $taille = max(14.0, round($image->width() / 45));
        $marge = (int) round($taille);
        $police = (string) config('photos.police');

        $image->text($texte, $image->width() - $marge, $image->height() - $marge, function (FontFactory $font) use ($taille, $police): void {
            if (is_file($police)) {
                $font->filepath($police);
            }
            $font->size($taille)
                ->color('#FFFFFF') // opaque : la bibliothèque l'exige dès qu'il y a un contour
                ->stroke('#1E3A5F', 2) // bleu nuit de la charte
                ->align(Alignment::RIGHT, Alignment::BOTTOM);
        });
    }

    private function gestionnaire(): ImageManager
    {
        return new ImageManager(Driver::class);
    }
}
