<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PhotosDeLogement;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\PhotoLogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Espace propriétaire › Photos d'un de SES logements (CdC § 7.1) : dépôt (10 à 30 par logement,
 * JPG/PNG, 5 Mo max), recadrage, ordre, couverture — chaque dépôt du propriétaire attend
 * l'acceptation de l'administration (App\Domain\Catalogue\Services\PhotosDeLogement::ajouter le pose déjà en « en_attente »).
 * Le propriétaire ne peut pas supprimer une photo ajoutée par l'administration (CdC § 7.1).
 */
final class PhotosController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function __construct(
        private readonly PhotosDeLogement $photos,
        private readonly Parametres $parametres,
    ) {}

    public function index(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        return ReponseApi::succes($this->galerie($logement));
    }

    public function ajouter(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        $tailleMaxKo = (int) $this->parametres->valeur('proprietaires.photo_taille_max_mo') * 1024;
        $saisie = $request->validate([
            'photo' => [
                'required',
                File::types(['jpg', 'jpeg', 'png'])->max($tailleMaxKo),
                Rule::dimensions()->minWidth(PhotosDeLogement::LARGEUR_MINIMALE)->minHeight(PhotosDeLogement::HAUTEUR_MINIMALE),
            ],
            'legende' => ['nullable', 'string', 'max:150'],
        ], [
            'photo.dimensions' => 'La photo est trop petite : il faut au moins '.PhotosDeLogement::LARGEUR_MINIMALE.' × '.PhotosDeLogement::HAUTEUR_MINIMALE.' pixels.',
        ], ['photo' => 'photo', 'legende' => 'légende']);

        /** @var User $auteur */
        $auteur = $request->user();
        $photo = $this->photos->ajouter($logement, $saisie['photo'], $saisie['legende'] ?? null, $auteur);

        return ReponseApi::cree(new PhotoLogementResource($photo), 'Photo déposée : elle attend l’acceptation de l’administration.');
    }

    public function definirCouverture(Request $request, Residence $residence, Logement $logement, PhotoLogement $photo): JsonResponse
    {
        $this->verifierLaPhoto($request, $residence, $logement, $photo);

        $this->photos->definirCouverture($photo);

        return ReponseApi::succes($this->galerie($logement), 'Photo de couverture désignée.');
    }

    public function reordonner(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        $saisie = $request->validate(['ordre' => ['required', 'array', 'min:1'], 'ordre.*' => ['integer', 'distinct']]);
        $this->photos->reordonner($logement, array_values(array_map('intval', $saisie['ordre'])));

        return ReponseApi::succes($this->galerie($logement), 'Ordre des photos enregistré.');
    }

    /** Refuse une photo ajoutée par l'administration : 403 (App\Domain\Catalogue\Services\PhotosDeLogement::supprimer le garantit déjà). */
    public function supprimer(Request $request, Residence $residence, Logement $logement, PhotoLogement $photo): JsonResponse
    {
        $this->verifierLaPhoto($request, $residence, $logement, $photo);

        $saisie = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:255']], [], ['motif' => 'motif']);

        /** @var User $auteur */
        $auteur = $request->user();
        $this->photos->supprimer($photo, $saisie['motif'], $auteur);

        return ReponseApi::succes($this->galerie($logement), 'Photo supprimée.');
    }

    /** @return array<string, mixed> */
    private function galerie(Logement $logement): array
    {
        $photos = $logement->photos()->orderBy('ordre')->orderBy('id')->get();
        $minimum = (int) $this->parametres->valeur('proprietaires.photos_minimum');

        return [
            'photos' => PhotoLogementResource::collection($photos),
            'nombre' => $photos->count(),
            'minimum' => $minimum,
            'maximum' => (int) $this->parametres->valeur('proprietaires.photos_maximum'),
            'assez_pour_publier' => $photos->where('etat', 'acceptee')->count() >= $minimum,
        ];
    }

    private function verifierLeLogement(Request $request, Residence $residence, Logement $logement): void
    {
        $proprietaire = $this->monProprietaire($request);

        if ($residence->proprietaire_id !== $proprietaire->id || $logement->residence_id !== $residence->id) {
            abort(404);
        }
    }

    private function verifierLaPhoto(Request $request, Residence $residence, Logement $logement, PhotoLogement $photo): void
    {
        $this->verifierLeLogement($request, $residence, $logement);
        if ($photo->logement_id !== $logement->id) {
            abort(404);
        }
    }
}
