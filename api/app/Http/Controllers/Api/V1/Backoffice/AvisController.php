<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\StatutAvis;
use App\Domain\Sejours\Models\Avis;
use App\Domain\Sejours\Services\GestionDesAvis;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\AvisResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** File de modération des avis vérifiés de fin de séjour (CdC § 5.1, P2-AVI-01). Administrateurs seulement. */
final class AvisController extends Controller
{
    private const RELATIONS = ['sejour.client', 'sejour.logement.residence'];

    public function __construct(private readonly GestionDesAvis $gestion) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, AvisResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $lignes = $this->requeteFiltree($filtres)->orderByDesc('id')->limit(5000)->get();

        $export = new ExportDeListe('Avis', [
            'residence' => 'Résidence', 'logement' => 'Logement', 'client' => 'Client',
            'note' => 'Note', 'statut' => 'Statut', 'cree_le' => 'Déposé le',
        ], $lignes->map(fn (Avis $a): array => [
            'residence' => $a->sejour->logement->residence->nom,
            'logement' => $a->sejour->logement->nom,
            'client' => $a->sejour->client ? $a->sejour->client->nomComplet() : '',
            'note' => (string) $a->note,
            'statut' => $a->statut->libelle(),
            'cree_le' => $a->created_at?->format('d/m/Y H:i') ?? '',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** Publier ou refuser : un avis n'est jamais visible du public avant cette décision. */
    public function decider(Request $request, Avis $avis): JsonResponse
    {
        $saisie = $request->validate([
            'decision' => ['required', Rule::in(['publier', 'refuser'])],
            'motif' => ['nullable', 'string', 'min:5', 'max:255', 'required_if:decision,refuser'],
        ], ['motif.required_if' => 'Un refus doit être motivé.'], ['decision' => 'décision', 'motif' => 'motif']);

        /** @var User $administrateur */
        $administrateur = $request->user();
        $avis = $saisie['decision'] === 'publier'
            ? $this->gestion->publier($avis, $administrateur)
            : $this->gestion->refuser($avis, (string) $saisie['motif'], $administrateur);

        return ReponseApi::succes(
            new AvisResource($avis->load(self::RELATIONS)),
            $saisie['decision'] === 'publier' ? 'Avis publié.' : 'Avis refusé.',
        );
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'statut' => ['nullable', Rule::enum(StatutAvis::class)],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Avis>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        $statut = $filtres['statut'] ?? StatutAvis::EnAttente->value;

        return Avis::query()->with(self::RELATIONS)->where('statut', $statut);
    }
}
