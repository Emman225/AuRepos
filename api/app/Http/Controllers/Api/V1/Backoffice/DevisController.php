<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Devis;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\DevisResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Direction › Devis établis, en attente de transformation (CdC § 9.1). Consultation seulement. */
final class DevisController extends Controller
{
    public function __construct(private readonly PerimetreGestionnaire $perimetre) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres, $request)->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, DevisResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $devis = $this->requeteFiltree($filtres, $request)->orderByDesc('id')->limit(5000)->get();

        $export = new ExportDeListe('Devis', [
            'reference' => 'Référence', 'client' => 'Client', 'logement' => 'Logement',
            'arrivee' => 'Arrivée', 'depart' => 'Départ', 'net_a_payer' => 'Net à payer', 'etat' => 'État',
        ], $devis->map(fn (Devis $d): array => [
            'reference' => $d->reference,
            'client' => $d->client->nomComplet(),
            'logement' => $d->logement->nom,
            'arrivee' => $d->arrivee->format('d/m/Y'),
            'depart' => $d->depart->format('d/m/Y'),
            'net_a_payer' => (string) $d->net_a_payer,
            'etat' => $d->etat,
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'etat' => ['nullable', Rule::in(['en_attente', 'transforme', 'archive'])],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Devis>
     */
    private function requeteFiltree(array $filtres, Request $request): Builder
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $autorisees = $this->perimetre->residencesAutorisees($utilisateur);

        $requete = Devis::query()->with(['client', 'logement.residence', 'sejour'])
            ->when($autorisees !== null, fn (Builder $q) => $q->whereHas('logement', fn (Builder $l) => $l->whereIn('residence_id', $autorisees ?? [])))
            ->when($filtres['etat'] ?? 'en_attente', fn (Builder $q, string $v) => $q->where('etat', $v));

        return FiltrePeriode::depuis($request)->appliquer($requete);
    }
}
