<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Extras\Enums\EtatDeCommandeExtra;
use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Extras\Models\Extra;
use App\Domain\Extras\Services\GestionDesExtras;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\CommandeExtraResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Commandes d'extras › suivi par la gestion quotidienne (P2-EXT-01) : la réception peut aussi
 * commander au nom du client (guichet, téléphone), confirmer, affecter un membre du personnel
 * qui l'exécute, marquer le service fait, refuser motivé. La facturation passe par le guichet
 * d'encaissement Extras (App\Http\Controllers\Api\V1\Backoffice\GuichetsController, P2-TRF-03).
 */
final class CommandesExtrasController extends Controller
{
    private const RELATIONS = ['sejour', 'extra', 'affecteA', 'demandePar'];

    public function __construct(private readonly GestionDesExtras $gestion) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'etat' => ['nullable', Rule::enum(EtatDeCommandeExtra::class)],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = CommandeExtra::query()->with(self::RELATIONS)
            ->when($filtres['etat'] ?? null, fn (Builder $q, string $v) => $q->where('etat', $v))
            ->orderByDesc('id')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, CommandeExtraResource::class);
    }

    /** La réception commande un extra au nom du client (guichet, téléphone) — CdC : « client ou staff ». */
    public function creer(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'sejour_id' => ['required', 'integer', Rule::exists('sejours', 'id')],
            'extra_id' => ['required', 'integer', Rule::exists('extras', 'id')],
            'quantite' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['sejour_id' => 'séjour', 'extra_id' => 'extra', 'quantite' => 'quantité', 'notes' => 'notes']);

        $sejour = Sejour::findOrFail($saisie['sejour_id']);
        $extra = Extra::findOrFail($saisie['extra_id']);

        /** @var User $agent */
        $agent = $request->user();
        $commande = $this->gestion->commander($sejour, $extra, (int) ($saisie['quantite'] ?? 1), $agent, $saisie['notes'] ?? null);

        return ReponseApi::cree(new CommandeExtraResource($commande->load(self::RELATIONS)), 'Commande d’extra enregistrée.');
    }

    public function confirmer(CommandeExtra $commande): JsonResponse
    {
        $commande = $this->gestion->confirmer($commande);

        return ReponseApi::succes(new CommandeExtraResource($commande->load(self::RELATIONS)), 'Commande confirmée.');
    }

    public function affecter(Request $request, CommandeExtra $commande): JsonResponse
    {
        $saisie = $request->validate([
            'membre_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ], [], ['membre_id' => 'membre du personnel']);

        $membre = User::findOrFail($saisie['membre_id']);
        $commande = $this->gestion->affecter($commande, $membre);

        return ReponseApi::succes(new CommandeExtraResource($commande->load(self::RELATIONS)), 'Commande affectée.');
    }

    public function marquerFournie(CommandeExtra $commande): JsonResponse
    {
        $commande = $this->gestion->marquerFournie($commande);

        return ReponseApi::succes(new CommandeExtraResource($commande->load(self::RELATIONS)), 'Service marqué fait.');
    }

    public function refuser(Request $request, CommandeExtra $commande): JsonResponse
    {
        $saisie = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:255']], [], ['motif' => 'motif']);

        $commande = $this->gestion->refuser($commande, (string) $saisie['motif']);

        return ReponseApi::succes(new CommandeExtraResource($commande->load(self::RELATIONS)), 'Commande refusée.');
    }
}
