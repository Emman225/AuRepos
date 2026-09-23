<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Models\PieceJustificative;
use App\Domain\Partenaires\Services\PiecesJustificatives;
use App\Domain\Sejours\Models\Client;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\PieceJustificativeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pièces du dossier d'un client à terme : dépôt, téléchargement, vérification (CdC § 5.3).
 *
 * {client} dans l'URL est l'utilisateur, comme partout ailleurs dans la caisse et les séjours ;
 * la fiche `Client` (nature, TVA, ligne de crédit) est résolue derrière. Pas de `scopeBindings()`
 * possible sur ce couple (User → Client → pièce) : chaque action revérifie donc elle-même que la
 * pièce appartient bien au client de l'URL, comme le fait la base pour un séjour ou un règlement.
 */
final class PiecesClientController extends Controller
{
    public function __construct(private readonly PiecesJustificatives $pieces) {}

    public function index(User $client): JsonResponse
    {
        return ReponseApi::succes(PieceJustificativeResource::collection(Client::de($client)->pieces()->latest('id')->get()));
    }

    public function deposer(Request $request, User $client): JsonResponse
    {
        $saisie = $request->validate([
            'type' => ['required', Rule::enum(TypeDePiece::class)],
            'fichier' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(PiecesJustificatives::TAILLE_MAX_KO)],
        ], [], ['type' => 'type de pièce', 'fichier' => 'fichier']);

        /** @var User $auteur */
        $auteur = $request->user();
        $piece = $this->pieces->deposer(Client::de($client), TypeDePiece::from($saisie['type']), $saisie['fichier'], null, $auteur);

        return ReponseApi::cree(new PieceJustificativeResource($piece), 'Pièce déposée. Elle attend sa vérification.');
    }

    /** Seule porte de sortie du fichier ; chaque consultation est tracée (donnée personnelle sensible). */
    public function telecharger(Request $request, User $client, PieceJustificative $piece, JournalAudit $journal): Response
    {
        $this->verifierAppartenance($client, $piece);
        $journal->consigner('consultation_piece', 'Consultation : '.$piece->libelleAudit().' de '.$client->nomComplet().'.', $piece);

        return response($this->pieces->contenu($piece), 200, [
            'Content-Type' => $piece->mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($piece->nom_original, '"\\').'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Valider ou refuser : réservé aux administrateurs, et jamais sur sa propre saisie. */
    public function decider(Request $request, User $client, PieceJustificative $piece): JsonResponse
    {
        $this->verifierAppartenance($client, $piece);

        /** @var User $verificateur */
        $verificateur = $request->user();
        if (! $verificateur->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }

        $saisie = $request->validate([
            'decision' => ['required', Rule::in(['valider', 'refuser'])],
            'motif' => ['nullable', 'string', 'min:5', 'max:255', 'required_if:decision,refuser'],
        ], ['motif.required_if' => 'Un refus doit être motivé.'], ['decision' => 'décision', 'motif' => 'motif']);

        $saisie['decision'] === 'valider'
            ? $this->pieces->valider($piece, $verificateur)
            : $this->pieces->refuser($piece, (string) $saisie['motif'], $verificateur);

        return ReponseApi::succes(new PieceJustificativeResource($piece->refresh()), 'Décision enregistrée.');
    }

    public function supprimer(User $client, PieceJustificative $piece): JsonResponse
    {
        $this->verifierAppartenance($client, $piece);
        $this->pieces->supprimer($piece);

        return ReponseApi::succes(null, 'Pièce supprimée.');
    }

    /** La pièce doit appartenir au client de l'URL : sinon 404, comme un séjour d'un autre client. */
    private function verifierAppartenance(User $client, PieceJustificative $piece): void
    {
        $ficheClient = Client::de($client);
        abort_if($piece->titulaire_type !== $ficheClient->getMorphClass() || $piece->titulaire_id !== $ficheClient->id, 404);
    }
}
