<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Tarification\Models\PrixNegocie;
use App\Domain\Tarification\Services\PrixNegocies;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\PrixNegocieResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Prix négociés par client et par type de logement : ils priment sur toute la grille (CdC § 7.3). */
final class PrixNegocieController extends Controller
{
    public function __construct(private readonly PrixNegocies $prixNegocies) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, PrixNegocieResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $prix = $this->requeteFiltree($filtres)->orderByDesc('id')->limit(5000)->get();

        $export = new ExportDeListe('Prix negocies', [
            'client' => 'Client', 'type' => 'Type de logement', 'tarif_par_nuit' => 'Tarif par nuit', 'actif' => 'Actif',
        ], $prix->map(fn (PrixNegocie $p): array => [
            'client' => $p->client->nomComplet(),
            'type' => $p->type->nom,
            'tarif_par_nuit' => (string) $p->tarif_par_nuit,
            'actif' => $p->actif ? 'Oui' : 'Non',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'client_id' => ['nullable', 'integer'],
            'actif' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<PrixNegocie>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return PrixNegocie::query()->with(['client', 'type'])
            ->when($filtres['client_id'] ?? null, fn (Builder $q, int $v) => $q->where('client_id', $v))
            ->when(array_key_exists('actif', $filtres) && $filtres['actif'] !== null, fn (Builder $q) => $q->where('actif', (bool) $filtres['actif']));
    }

    public function enregistrer(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'type_logement_id' => ['required', 'integer', Rule::exists('types_logement', 'id')],
            'tarif_par_nuit' => ['required', 'integer', 'min:1', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['client_id' => 'client', 'type_logement_id' => 'type de logement', 'tarif_par_nuit' => 'tarif par nuit', 'notes' => 'notes']);

        /** @var User $auteur */
        $auteur = $request->user();
        $client = User::findOrFail($saisie['client_id']);
        $prixNegocie = $this->prixNegocies->enregistrer($client, (int) $saisie['type_logement_id'], (int) $saisie['tarif_par_nuit'], $saisie['notes'] ?? null, $auteur);

        return ReponseApi::cree(new PrixNegocieResource($prixNegocie->load(['client', 'type'])), 'Prix négocié enregistré. Il prime désormais sur la grille pour ce client et ce type.');
    }

    public function desactiver(PrixNegocie $prixNegocie): JsonResponse
    {
        $this->prixNegocies->desactiver($prixNegocie);

        return ReponseApi::succes(new PrixNegocieResource($prixNegocie->refresh()->load(['client', 'type'])), 'Prix négocié désactivé : la grille standard s’applique de nouveau.');
    }
}
