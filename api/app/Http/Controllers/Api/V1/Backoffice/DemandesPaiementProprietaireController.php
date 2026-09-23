<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\DemandePaiementProprietaire;
use App\Domain\Partenaires\Services\DemandesPaiementProprietaire;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Propriétaires › Demandes de paiement (back office, CdC § 8.7 et § 10, P3-PRO-04) : rejet, ou
 * décaissement qui réutilise App\Domain\Caisse\Services\Caisse::saisirUnDecaissement — le même
 * circuit de preuve à quatre étapes que tout décaissement de la caisse.
 */
final class DemandesPaiementProprietaireController extends Controller
{
    public function __construct(private readonly DemandesPaiementProprietaire $demandes) {}

    public function index(Request $request): JsonResponse
    {
        $statut = $request->query('etat');

        $demandes = DemandePaiementProprietaire::query()->with('proprietaire.utilisateur')
            ->when($statut, fn (Builder $q, string $s) => $q->where('etat', $s))
            ->orderByDesc('demande_le')->get();

        return ReponseApi::succes($demandes->map(fn (DemandePaiementProprietaire $d): array => [
            'id' => $d->id, 'proprietaire' => $d->proprietaire->nomAffiche(), 'montant' => $d->montant,
            'etat' => $d->etat, 'demande_le' => $d->demande_le->format('d/m/Y H:i'),
            'retenue_taux' => $d->retenue_taux, 'retenue_montant' => $d->retenue_montant,
        ]));
    }

    public function rejeter(Request $request, DemandePaiementProprietaire $demande): JsonResponse
    {
        $saisie = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:255']], [], ['motif' => 'motif']);

        /** @var User $administrateur */
        $administrateur = $request->user();
        $this->demandes->rejeter($demande, $administrateur, $saisie['motif']);

        return ReponseApi::succes(null, 'Demande rejetée.');
    }

    public function decaisser(Request $request, DemandePaiementProprietaire $demande): JsonResponse
    {
        $saisie = $request->validate([
            'mode' => ['required', Rule::in(['especes', 'mobile_money', 'virement', 'cheque'])],
            'reference_du_mode' => ['nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['mode' => 'mode de règlement', 'notes' => 'notes / observations']);

        /** @var User $administrateur */
        $administrateur = $request->user();
        $reglement = $this->demandes->decaisser(
            $demande, $administrateur, ModeDeReglement::from($saisie['mode']), $saisie['notes'], $saisie['reference_du_mode'] ?? null,
        );

        return ReponseApi::cree(['reglement_reference' => $reglement->reference], 'Décaissement saisi. Il suit le même circuit de preuve.');
    }
}
