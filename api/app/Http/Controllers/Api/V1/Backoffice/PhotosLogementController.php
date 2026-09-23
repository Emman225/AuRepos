<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PhotosDeLogement;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\PhotoLogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Photos d'un logement, côté administration (CdC § 7.1) : ajouter, légender,
 * recadrer, ordonner, désigner la couverture, supprimer avec motif.
 */
final class PhotosLogementController extends Controller
{
    public function __construct(
        private readonly PhotosDeLogement $photos,
        private readonly Parametres $parametres,
    ) {}

    public function index(Residence $residence, Logement $logement): JsonResponse
    {
        return ReponseApi::succes($this->galerie($logement));
    }

    public function ajouter(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
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

        return ReponseApi::cree(new PhotoLogementResource($photo), 'Photo ajoutée.');
    }

    public function modifier(Request $request, Residence $residence, Logement $logement, PhotoLogement $photo): JsonResponse
    {
        $saisie = $request->validate([
            'legende' => ['sometimes', 'nullable', 'string', 'max:150'],
            'couverture' => ['sometimes', 'accepted'],
        ], [], ['legende' => 'légende']);

        if (array_key_exists('legende', $saisie)) {
            $photo->update(['legende' => $saisie['legende']]);
        }
        if ($saisie['couverture'] ?? false) {
            $this->photos->definirCouverture($photo);
        }

        return ReponseApi::succes($this->galerie($logement), 'Photo modifiée.');
    }

    public function recadrer(Request $request, Residence $residence, Logement $logement, PhotoLogement $photo): JsonResponse
    {
        $cadre = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'largeur' => ['required', 'integer', 'min:'.PhotosDeLogement::LARGEUR_MINIMALE],
            'hauteur' => ['required', 'integer', 'min:'.PhotosDeLogement::HAUTEUR_MINIMALE],
        ]);

        $photo = $this->photos->recadrer($photo, $cadre['x'], $cadre['y'], $cadre['largeur'], $cadre['hauteur']);

        return ReponseApi::succes(new PhotoLogementResource($photo), 'Photo recadrée.');
    }

    public function reordonner(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $saisie = $request->validate(['ordre' => ['required', 'array', 'min:1'], 'ordre.*' => ['integer', 'distinct']]);

        // array_values : le client peut envoyer un tableau à clés ; le service attend une liste.
        $this->photos->reordonner($logement, array_values(array_map('intval', $saisie['ordre'])));

        return ReponseApi::succes($this->galerie($logement), 'Ordre des photos enregistré.');
    }

    public function supprimer(Request $request, Residence $residence, Logement $logement, PhotoLogement $photo): JsonResponse
    {
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
            // Un logement ne pourra être publié qu'avec assez de photos (contrôle posé par P3-PUB).
            'assez_pour_publier' => $photos->count() >= $minimum,
        ];
    }
}
