<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\StatutDemandeATerme;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Services\ComptesATerme;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientATermeResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Dossiers de client à terme : instruction des demandes (CdC § 5.1, 5.3). */
final class ComptesATermeController extends Controller
{
    public function __construct(private readonly ComptesATerme $comptesATerme) {}

    /** Par défaut, la file à instruire ; ?statut= pour voir les autres. */
    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ClientATermeResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $demandes = $this->requeteFiltree($filtres)->limit(5000)->get();

        $export = new ExportDeListe('Clients a terme', [
            'nom' => 'Nom', 'nature' => 'Nature', 'raison_sociale' => 'Raison sociale',
            'plafond_credit' => 'Plafond de crédit', 'statut' => 'Statut',
        ], $demandes->map(fn (Client $c): array => [
            'nom' => $c->utilisateur->nomComplet(),
            'nature' => $c->nature,
            'raison_sociale' => $c->raison_sociale ?? '',
            'plafond_credit' => (string) $c->plafond_credit,
            'statut' => $c->statut_a_terme->value,
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'statut' => ['nullable', Rule::enum(StatutDemandeATerme::class)],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Client>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        $statut = $filtres['statut'] ?? StatutDemandeATerme::EnAttente->value;

        return Client::query()->with(['utilisateur', 'pieces'])
            ->where('statut_a_terme', $statut)
            ->orderByDesc('demande_a_terme_le');
    }

    public function afficher(User $client): JsonResponse
    {
        return ReponseApi::succes(new ClientATermeResource(Client::de($client)->load(['utilisateur', 'pieces'])));
    }

    /** Accepter ou refuser : réservé aux administrateurs. */
    public function decider(Request $request, User $client): JsonResponse
    {
        /** @var User $administrateur */
        $administrateur = $request->user();
        if (! $administrateur->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }

        $saisie = $request->validate([
            'decision' => ['required', Rule::in(['accepter', 'refuser'])],
            'plafond_credit' => ['required_if:decision,accepter', 'integer', 'min:0'],
            'motif' => ['nullable', 'string', 'min:5', 'max:255', 'required_if:decision,refuser'],
        ], ['motif.required_if' => 'Un refus doit être motivé.', 'plafond_credit.required_if' => 'Indiquez le plafond de crédit (0 = aucune limite).'], [
            'decision' => 'décision', 'plafond_credit' => 'plafond de crédit', 'motif' => 'motif',
        ]);

        $ficheClient = Client::de($client);
        $ficheClient = $saisie['decision'] === 'accepter'
            ? $this->comptesATerme->accepter($ficheClient, (int) $saisie['plafond_credit'], $administrateur)
            : $this->comptesATerme->refuser($ficheClient, (string) $saisie['motif'], $administrateur);

        return ReponseApi::succes(
            new ClientATermeResource($ficheClient->load('pieces')),
            $saisie['decision'] === 'accepter' ? 'Compte à crédit accepté.' : 'Demande refusée.',
        );
    }
}
