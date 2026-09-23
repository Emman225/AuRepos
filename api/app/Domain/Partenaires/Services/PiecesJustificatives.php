<?php

namespace App\Domain\Partenaires\Services;

use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\StatutDePiece;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Models\PieceJustificative;
use App\Support\Api\ErreurMetier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pièces d'un dossier : pièce d'identité, titre de propriété, RIB, mandat, DFE…
 *
 * Ce sont des données personnelles sensibles (loi ivoirienne, ARTCI — CdC § 13.2) :
 * le fichier est CHIFFRÉ avant d'être écrit sur le disque privé. Une copie du disque
 * ou une sauvegarde volée ne livre rien de lisible sans la clé de l'application.
 */
final class PiecesJustificatives
{
    public const TAILLE_MAX_KO = 5 * 1024;

    public function deposer(Model $titulaire, TypeDePiece $type, UploadedFile $fichier, ?Carbon $expireLe, User $auteur): PieceJustificative
    {
        $chemin = 'pieces/'.Str::slug(class_basename($titulaire)).'/'.$titulaire->getKey().'/'.Str::uuid().'.chiffre';

        Storage::disk('local')->put($chemin, Crypt::encryptString((string) file_get_contents($fichier->getRealPath())));

        return PieceJustificative::create([
            'titulaire_type' => $titulaire->getMorphClass(),
            'titulaire_id' => $titulaire->getKey(),
            'type' => $type,
            'chemin' => $chemin,
            'nom_original' => mb_substr($fichier->getClientOriginalName(), 0, 255),
            'mime' => (string) $fichier->getMimeType(),
            'taille_octets' => (int) $fichier->getSize(),
            'expire_le' => $expireLe,
            'deposee_par' => $auteur->id,
            // Une pièce n'est jamais valable d'office : un administrateur la vérifie.
            'statut' => StatutDePiece::EnAttente,
        ]);
    }

    /** Contenu en clair, pour le seul téléchargement autorisé. */
    public function contenu(PieceJustificative $piece): string
    {
        return Crypt::decryptString((string) Storage::disk('local')->get($piece->chemin));
    }

    public function valider(PieceJustificative $piece, User $verificateur): void
    {
        $this->refuserSaPropreSaisie($piece, $verificateur);

        $piece->update([
            'statut' => StatutDePiece::Validee, 'motif_refus' => null,
            'verifiee_par' => $verificateur->id, 'verifiee_le' => now(),
        ]);
    }

    public function refuser(PieceJustificative $piece, string $motif, User $verificateur): void
    {
        $piece->update([
            'statut' => StatutDePiece::Refusee, 'motif_refus' => $motif,
            'verifiee_par' => $verificateur->id, 'verifiee_le' => now(),
        ]);
    }

    public function supprimer(PieceJustificative $piece): void
    {
        $piece->delete();
        Storage::disk('local')->delete($piece->chemin);
    }

    /** Même logique que la double validation de la caisse : on ne valide pas ce qu'on a soi-même déposé. */
    private function refuserSaPropreSaisie(PieceJustificative $piece, User $verificateur): void
    {
        if ($piece->getAttribute('deposee_par') === $verificateur->id) {
            throw new ErreurMetier(
                'Vous avez déposé cette pièce : un autre administrateur doit la valider.',
                'validation_de_sa_propre_saisie',
                403,
            );
        }
    }
}
