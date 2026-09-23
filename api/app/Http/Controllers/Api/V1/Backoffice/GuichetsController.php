<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Caisse\Services\SoldeDesConsommations;
use App\Domain\Comptes\Models\User;
use App\Domain\Extras\Enums\EtatDeCommandeExtra;
use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Enums\EtatDuTransfert;
use App\Domain\Transferts\Models\Transfert;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ReglementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guichets d'encaissement Extras et Transferts (P2-TRF-03) : ce qui reste dû sur les
 * consommations demandées PENDANT un séjour — jamais une ligne du net à payer figé du séjour
 * (App\Domain\Sejours\Services\CheckOut::consommations). Même mécanisme générique que la
 * caisse des séjours (App\Domain\Caisse\Services\Caisse::encaisserUneConsommation), imputé sur
 * la consommation elle-même via imputations_reglement, déjà polymorphe.
 */
final class GuichetsController extends Controller
{
    public function __construct(
        private readonly Caisse $caisse,
        private readonly SoldeDesConsommations $soldes,
    ) {}

    /** Transferts non annulés avec un reste dû : ce que le guichet a encore à encaisser. */
    public function transferts(): JsonResponse
    {
        $transferts = Transfert::query()->with(['sejour.client'])
            ->where('etat', '<>', EtatDuTransfert::Annule->value)
            ->orderBy('date_heure_prevue')
            ->get();

        return ReponseApi::succes($this->enAttente($transferts, fn (Transfert $t) => $t->montant));
    }

    public function encaisserTransfert(Request $request, Transfert $transfert): JsonResponse
    {
        return $this->encaisser($request, $transfert, $transfert->montant, Guichet::Transferts);
    }

    /** Commandes d'extras non mortes avec un reste dû : ce que le guichet a encore à encaisser. */
    public function extras(): JsonResponse
    {
        $commandes = CommandeExtra::query()->with(['sejour.client', 'extra'])
            ->whereNotIn('etat', [EtatDeCommandeExtra::Annulee->value, EtatDeCommandeExtra::Refusee->value])
            ->orderByDesc('id')
            ->get();

        return ReponseApi::succes($this->enAttente($commandes, fn (CommandeExtra $c) => $c->montant_total));
    }

    public function encaisserExtra(Request $request, CommandeExtra $commande): JsonResponse
    {
        return $this->encaisser($request, $commande, $commande->montant_total, Guichet::Extras);
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $consommations
     * @param  callable(TModel): int  $montantDu
     * @return list<array<string, mixed>>
     */
    private function enAttente(Collection $consommations, callable $montantDu): array
    {
        return $consommations
            ->map(function ($consommation) use ($montantDu): array {
                $du = $montantDu($consommation);
                $sejour = $consommation->sejour;

                return [
                    'id' => $consommation->id,
                    'reference' => $consommation->reference,
                    'sejour' => ['id' => $sejour->id, 'reference' => $sejour->reference],
                    'client' => $sejour->client !== null ? ['id' => $sejour->client->id, 'nom' => $sejour->client->nomComplet()] : null,
                    'etat' => $consommation->etat->value,
                    'etat_libelle' => $consommation->etat->libelle(),
                    ...$this->soldes->de($consommation, $du),
                ];
            })
            ->filter(fn (array $c) => $c['reste_du'] > 0)
            ->values()
            ->all();
    }

    private function encaisser(Request $request, Model $consommation, int $montantDu, Guichet $guichet): JsonResponse
    {
        $saisie = $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'mode' => ['required', Rule::in(['especes', 'mobile_money', 'carte', 'virement', 'cheque'])],
            'reference_du_mode' => ['nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['montant' => 'montant', 'mode' => 'mode de règlement', 'notes' => 'notes / observations']);

        /** @var User $caissier */
        $caissier = $request->user();
        /** @var Sejour $sejour */
        $sejour = $consommation->sejour;
        $client = $sejour->client;
        if ($client === null) {
            abort(422, 'Ce séjour n’a pas de client rattaché : impossible d’encaisser.');
        }

        $reglement = $this->caisse->encaisserUneConsommation(
            $caissier, $consommation, $montantDu, $client, (int) $saisie['montant'],
            ModeDeReglement::from($saisie['mode']), $saisie['notes'], $guichet, $saisie['reference_du_mode'] ?? null,
        );

        return ReponseApi::cree(new ReglementResource($reglement->load(['agence', 'tiers', 'auteur', 'imputations.affaire'])), 'Encaissement saisi. Il ne comptera qu’une fois validé, prouvé et finalisé.');
    }
}
