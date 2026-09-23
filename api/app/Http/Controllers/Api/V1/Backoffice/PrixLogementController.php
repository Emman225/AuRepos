<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\NegociationPrix;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PourcentageEntreprise;
use App\Domain\Catalogue\Services\PrixDeLogement;
use App\Domain\Comptes\Models\User;
use App\Domain\Validation\Models\ChangementAValider;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ChangementAValiderResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Prix d'un logement : prix propriétaire négocié, prix de vente en double validation (CdC § 7.2). */
final class PrixLogementController extends Controller
{
    public function __construct(
        private readonly PrixDeLogement $prix,
        private readonly PourcentageEntreprise $pourcentageEntreprise,
    ) {}

    public function afficher(Residence $residence, Logement $logement): JsonResponse
    {
        return ReponseApi::succes($this->situation($logement));
    }

    /** Historique de négociation : une proposition, ou l'accord qui arrête le prix propriétaire. */
    public function prixProprietaire(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
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
            $saisie['nature'] === 'accord' ? 'Prix propriétaire arrêté.' : 'Proposition ajoutée à l’historique.',
        );
    }

    /** Un administrateur PROPOSE un prix de vente ; il n'entrera en vigueur qu'après validation par un autre. */
    public function prixDeVente(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $saisie = $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['montant' => 'montant', 'motif' => 'motif']);

        /** @var User $administrateur */
        $administrateur = $request->user();
        $this->prix->proposerLePrixDeVente($logement, (int) $saisie['montant'], $saisie['motif'] ?? null, $administrateur);

        return ReponseApi::cree(
            $this->situation($logement->refresh()),
            'Prix de vente proposé. Il entrera en vigueur après validation par un second administrateur.',
        );
    }

    /** Dérogation au pourcentage entreprise pour CE logement (CdC § 7.3) — même double validation que le prix de vente. */
    public function pourcentageEntreprise(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $saisie = $request->validate([
            'taux' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['taux' => 'taux', 'motif' => 'motif']);

        /** @var User $administrateur */
        $administrateur = $request->user();
        $this->pourcentageEntreprise->proposerUneDerogation(
            $logement, isset($saisie['taux']) ? (float) $saisie['taux'] : null, $saisie['motif'] ?? null, $administrateur,
        );

        return ReponseApi::cree(
            $this->situation($logement->refresh()),
            'Dérogation proposée. Elle entrera en vigueur après validation par un second administrateur.',
        );
    }

    /** @return array<string, mixed> */
    private function situation(Logement $logement): array
    {
        $enAttente = fn (string $champ) => ChangementAValider::query()->with('auteur')
            ->where('sujet_type', $logement->getMorphClass())->where('sujet_id', $logement->id)
            ->where('champ', $champ)->where('statut', 'en_attente')->first();

        $venteEnAttente = $enAttente(PrixDeLogement::CHAMP_PRIX_DE_VENTE);
        $derogationEnAttente = $enAttente('pourcentage_entreprise_derogation');

        return [
            ...$this->prix->situation($logement),
            'changement_en_attente' => $venteEnAttente ? new ChangementAValiderResource($venteEnAttente) : null,
            'derogation_en_attente' => $derogationEnAttente ? new ChangementAValiderResource($derogationEnAttente) : null,
            'negociation' => NegociationPrix::query()->with('auteur')->where('logement_id', $logement->id)->orderByDesc('id')->get()
                ->map(fn (NegociationPrix $n): array => [
                    'id' => $n->id, 'date' => $n->created_at?->format('d/m/Y H:i:s'), 'partie' => $n->partie,
                    'auteur' => $n->auteur->nomComplet(), 'montant' => $n->montant, 'nature' => $n->nature, 'commentaire' => $n->commentaire,
                ]),
        ];
    }
}
