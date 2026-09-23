<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Models\PieceJustificative;
use App\Domain\Partenaires\Services\PiecesJustificatives;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\PieceJustificativeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\Response;

/** Pièces du dossier d'un propriétaire : dépôt, téléchargement, vérification. */
final class PiecesProprietaireController extends Controller
{
    public function __construct(private readonly PiecesJustificatives $pieces) {}

    public function index(Proprietaire $proprietaire): JsonResponse
    {
        return ReponseApi::succes(PieceJustificativeResource::collection($proprietaire->pieces()->latest('id')->get()));
    }

    public function deposer(Request $request, Proprietaire $proprietaire): JsonResponse
    {
        $saisie = $request->validate([
            'type' => ['required', Rule::enum(TypeDePiece::class)],
            'fichier' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(PiecesJustificatives::TAILLE_MAX_KO)],
            'expire_le' => ['nullable', 'date', 'after:today'],
        ], [], ['type' => 'type de pièce', 'fichier' => 'fichier', 'expire_le' => 'date d’expiration']);

        /** @var User $auteur */
        $auteur = $request->user();
        $piece = $this->pieces->deposer(
            $proprietaire,
            TypeDePiece::from($saisie['type']),
            $saisie['fichier'],
            isset($saisie['expire_le']) ? Carbon::parse($saisie['expire_le']) : null,
            $auteur,
        );

        return ReponseApi::cree(new PieceJustificativeResource($piece), 'Pièce déposée. Elle attend sa vérification.');
    }

    /** Seule porte de sortie du fichier ; chaque consultation est tracée (donnée personnelle sensible). */
    public function telecharger(Request $request, Proprietaire $proprietaire, PieceJustificative $piece, JournalAudit $journal): Response
    {
        $journal->consigner('consultation_piece', 'Consultation : '.$piece->libelleAudit().' de '.$proprietaire->libelleAudit().'.', $piece);

        return response($this->pieces->contenu($piece), 200, [
            'Content-Type' => $piece->mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($piece->nom_original, '"\\').'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Valider ou refuser : réservé aux administrateurs, et jamais sur sa propre saisie. */
    public function decider(Request $request, Proprietaire $proprietaire, PieceJustificative $piece): JsonResponse
    {
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

    public function supprimer(Proprietaire $proprietaire, PieceJustificative $piece): JsonResponse
    {
        $this->pieces->supprimer($piece);

        return ReponseApi::succes(null, 'Pièce supprimée.');
    }
}
