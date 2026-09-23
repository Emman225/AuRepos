<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\PaiementEnLigne\Models\PaiementEnLigne;
use App\Domain\PaiementEnLigne\Services\PaiementsEnLigne;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Paiement en ligne (CdC § 5.2). Le client déclenche ; le serveur seul conclut. */
final class PaiementsController extends Controller
{
    public function __construct(private readonly PaiementsEnLigne $paiements) {}

    /** Le client demande à payer : il reçoit l'adresse de la passerelle, jamais une clé. */
    public function initier(Request $request, string $reference): JsonResponse
    {
        $saisie = $request->validate(['montant' => ['nullable', 'integer', 'min:1']], [], ['montant' => 'montant']);

        $sejour = Sejour::query()->where('reference', $reference)->where('client_id', $request->user()?->getAuthIdentifier())->firstOrFail();
        $paiement = $this->paiements->initier($sejour, isset($saisie['montant']) ? (int) $saisie['montant'] : null);

        return ReponseApi::cree([
            'reference' => $paiement->reference,
            'montant' => $paiement->montant,
            'url_paiement' => $paiement->url_paiement,
        ], 'Vous allez être redirigé vers la page de paiement sécurisée.');
    }

    /**
     * Rappel de la passerelle. Route PUBLIQUE : la passerelle n'a pas de session.
     * Son authenticité tient à la signature, et son contenu n'est jamais cru sur parole.
     */
    public function rappel(Request $request): JsonResponse
    {
        $paiement = $this->paiements->traiterLeRappel(
            $request->all(),
            $request->header('X-Signature') ?? $request->header('X-Paysecure-Signature'),
        );

        // La passerelle attend un accusé ; elle n'a pas à connaître notre métier.
        return ReponseApi::succes(['reference' => $paiement->reference, 'etat' => $paiement->etat], 'Rappel reçu.');
    }

    /**
     * Le client revient de la passerelle : on redemande l'état RÉEL, de serveur à serveur.
     * C'est le filet quand le rappel n'est pas arrivé.
     */
    public function etat(Request $request, string $reference): JsonResponse
    {
        $paiement = PaiementEnLigne::query()->whereKey($reference)->where('client_id', $request->user()?->getAuthIdentifier())->firstOrFail();
        $paiement = $this->paiements->verifier($paiement);

        return ReponseApi::succes([
            'reference' => $paiement->reference,
            'etat' => $paiement->etat,
            'montant' => $paiement->montant,
            'sejour' => $paiement->sejour->reference,
            'numero_recu' => $paiement->reglement?->numero_recu,
            'motif' => $paiement->motif_echec,
        ], match ($paiement->etat) {
            'reussi' => 'Paiement confirmé. Votre reçu vous a été envoyé.',
            'echoue' => 'Le paiement n’a pas abouti. Vous pouvez réessayer ou régler en agence.',
            'expire' => 'Le délai de paiement est dépassé. Vous pouvez recommencer.',
            default => 'Paiement en cours de confirmation. Cette page se met à jour automatiquement.',
        });
    }
}
