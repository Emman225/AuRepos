<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Services\PiecesJustificatives;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Services\ComptesATerme;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientATermeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** Espace client › Devenir client à terme (CdC § 5.1, 5.3). */
final class CompteController extends Controller
{
    public function __construct(
        private readonly ComptesATerme $comptesATerme,
        private readonly PiecesJustificatives $pieces,
    ) {}

    /** Détail du compte (CdC § 5.3) : coordonnées et régime de facturation. En lecture seule — leur modification passe par la réception. */
    public function monCompte(Request $request): JsonResponse
    {
        $utilisateur = $this->moi($request);
        $client = Client::de($utilisateur);

        return ReponseApi::succes([
            'nom' => $utilisateur->nom,
            'prenoms' => $utilisateur->prenoms,
            'nom_complet' => $utilisateur->nomComplet(),
            'email' => $utilisateur->email,
            'telephone' => $utilisateur->telephone,
            'nature' => $client->nature,
            'nature_libelle' => Client::NATURES[$client->nature] ?? $client->nature,
            'raison_sociale' => $client->raison_sociale,
            'ncc' => $client->ncc,
            'tva_hebergement' => $client->tva_hebergement,
            'tva_transfert' => $client->tva_transfert,
        ]);
    }

    /** Ma situation : statut, plafond, encours si accepté, pièces déjà déposées. */
    public function aTerme(Request $request): JsonResponse
    {
        $client = Client::de($this->moi($request))->load('pieces');

        return ReponseApi::succes(new ClientATermeResource($client));
    }

    public function demanderATerme(Request $request): JsonResponse
    {
        $client = $this->comptesATerme->demander($this->moi($request));

        return ReponseApi::cree(
            new ClientATermeResource($client->load('pieces')),
            'Demande enregistrée. Déposez vos pièces (RCCM, bilan, pièce d’identité) pour que le dossier soit instruit.',
        );
    }

    public function deposerUnePiece(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'type' => ['required', Rule::in(['rccm', 'bilan', 'piece_identite'])],
            'fichier' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(PiecesJustificatives::TAILLE_MAX_KO)],
        ], [], ['type' => 'type de pièce', 'fichier' => 'fichier']);

        $utilisateur = $this->moi($request);
        $client = Client::de($utilisateur);
        $this->pieces->deposer($client, TypeDePiece::from($saisie['type']), $saisie['fichier'], null, $utilisateur);

        return ReponseApi::cree(new ClientATermeResource($client->refresh()->load('pieces')), 'Pièce déposée. Elle attend sa vérification.');
    }

    private function moi(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }
}
