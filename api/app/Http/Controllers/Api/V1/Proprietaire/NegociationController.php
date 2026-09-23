<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\NegociationPrix;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PrixDeLogement;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Espace propriétaire › Négociation du prix de SON logement (CdC § 7.1 et § 7.2) : propositions
 * et contre-propositions historisées jusqu'à l'accord — le propriétaire ACCEPTE ou CONTRE-PROPOSE,
 * jamais n'arrête un prix au-dessus du prix de vente en vigueur (App\Domain\Catalogue\Services\PrixDeLogement le garantit déjà).
 */
final class NegociationController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function __construct(private readonly PrixDeLogement $prix) {}

    public function afficher(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        return ReponseApi::succes($this->situation($logement));
    }

    public function proposer(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $this->verifierLeLogement($request, $residence, $logement);

        $saisie = $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
            'nature' => ['required', Rule::in(['proposition', 'accord'])],
            'commentaire' => ['nullable', 'string', 'max:255'],
        ], [], ['montant' => 'montant', 'nature' => 'nature', 'commentaire' => 'commentaire']);

        /** @var User $auteur */
        $auteur = $request->user();
        $saisie['nature'] === 'accord'
            ? $this->prix->arreterLePrixProprietaire($logement, (int) $saisie['montant'], $saisie['commentaire'] ?? null, $auteur)
            : $this->prix->noterUneProposition($logement, (int) $saisie['montant'], $saisie['commentaire'] ?? null, $auteur);

        return ReponseApi::succes(
            $this->situation($logement->refresh()),
            $saisie['nature'] === 'accord' ? 'Prix accepté : il devient le prix propriétaire arrêté.' : 'Contre-proposition ajoutée à l’historique.',
        );
    }

    /** @return array<string, mixed> */
    private function situation(Logement $logement): array
    {
        return [
            'prix_proprietaire' => $logement->prix_proprietaire,
            'negociation' => NegociationPrix::query()->with('auteur')->where('logement_id', $logement->id)->orderByDesc('id')->get()
                ->map(fn (NegociationPrix $n): array => [
                    'id' => $n->id, 'date' => $n->created_at?->format('d/m/Y H:i:s'), 'partie' => $n->partie,
                    'auteur' => $n->auteur->nomComplet(), 'montant' => $n->montant, 'nature' => $n->nature, 'commentaire' => $n->commentaire,
                ]),
        ];
    }

    private function verifierLeLogement(Request $request, Residence $residence, Logement $logement): void
    {
        $proprietaire = $this->monProprietaire($request);
        if ($residence->proprietaire_id !== $proprietaire->id || $logement->residence_id !== $residence->id) {
            abort(404);
        }
    }
}
