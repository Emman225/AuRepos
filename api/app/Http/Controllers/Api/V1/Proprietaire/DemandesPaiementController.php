<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\DemandePaiementProprietaire;
use App\Domain\Partenaires\Services\DemandesPaiementProprietaire;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace propriétaire › Demandes de paiement, plafonnées au solde net (CdC § 8.7 et § 10, P3-PRO-04). */
final class DemandesPaiementController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function __construct(private readonly DemandesPaiementProprietaire $demandes) {}

    public function index(Request $request): JsonResponse
    {
        $proprietaire = $this->monProprietaire($request);

        $demandes = DemandePaiementProprietaire::query()->where('proprietaire_id', $proprietaire->id)->orderByDesc('demande_le')->get();

        return ReponseApi::succes($demandes->map(fn (DemandePaiementProprietaire $d): array => $this->presenter($d)));
    }

    public function creer(Request $request): JsonResponse
    {
        $proprietaire = $this->monProprietaire($request);
        $saisie = $request->validate(['montant' => ['required', 'integer', 'min:1', 'max:100000000']], [], ['montant' => 'montant']);

        /** @var User $auteur */
        $auteur = $request->user();
        $demande = $this->demandes->demander($proprietaire, (int) $saisie['montant'], $auteur);

        return ReponseApi::cree($this->presenter($demande), 'Demande de paiement enregistrée.');
    }

    /** @return array<string, mixed> */
    private function presenter(DemandePaiementProprietaire $demande): array
    {
        return [
            'id' => $demande->id, 'montant' => $demande->montant, 'etat' => $demande->etat,
            'demande_le' => $demande->demande_le->format('d/m/Y H:i'), 'motif_rejet' => $demande->motif_rejet,
            'retenue_taux' => $demande->retenue_taux, 'retenue_montant' => $demande->retenue_montant,
        ];
    }
}
