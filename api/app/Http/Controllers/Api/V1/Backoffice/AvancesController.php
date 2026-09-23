<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\AvanceClient;
use App\Domain\Caisse\Services\Avances;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ReglementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Guichet des avances (CdC § 4) : dépôt sans réservation, et situation du compte d'un client. */
final class AvancesController extends Controller
{
    public function __construct(
        private readonly Caisse $caisse,
        private readonly Avances $avances,
    ) {}

    public function situation(User $client): JsonResponse
    {
        $depots = AvanceClient::query()->with('reglement')->where('client_id', $client->id)->orderByDesc('id')->get()
            ->map(fn (AvanceClient $a): array => [
                'id' => $a->id,
                'date' => $a->created_at?->format('d/m/Y H:i:s'),
                'montant' => $a->montant,
                'solde' => $a->solde,
                'utilise' => $a->montant - $a->solde,
                'numero_recu' => $a->reglement->numero_recu,
            ]);

        return ReponseApi::succes([
            'client' => ['id' => $client->id, 'nom' => $client->nomComplet()],
            // Ce qu'il reste au client, utilisable d'office sur ses prochains séjours réglés hors ligne.
            'disponible' => $this->avances->disponible($client),
            'depots' => $depots,
        ]);
    }

    public function deposer(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'mode' => ['required', Rule::in(['especes', 'mobile_money', 'carte', 'virement', 'cheque'])],
            'reference_du_mode' => ['nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['client_id' => 'client', 'montant' => 'montant', 'mode' => 'mode de règlement', 'notes' => 'notes / observations']);

        /** @var User $caissier */
        $caissier = $request->user();
        $reglement = $this->caisse->saisirUnDepotDAvance(
            $caissier, User::findOrFail($saisie['client_id']), (int) $saisie['montant'],
            ModeDeReglement::from($saisie['mode']), $saisie['notes'], $saisie['reference_du_mode'] ?? null,
        );

        return ReponseApi::cree(
            new ReglementResource($reglement->load(['agence', 'tiers', 'auteur'])),
            'Dépôt d’avance saisi. Il suit le circuit de preuve ; l’avance sera utilisable une fois le règlement effectué.',
        );
    }
}
