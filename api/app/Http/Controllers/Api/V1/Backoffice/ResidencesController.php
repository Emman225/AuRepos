<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\ResidenceRequest;
use App\Http\Resources\Backoffice\ResidenceResource;
use App\Support\Api\ErreurMetier;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Catalogue › Résidences (back office). L'administrateur voit TOUTES les résidences ; le gestionnaire, seulement les siennes (CdC § 9.5). */
final class ResidencesController extends Controller
{
    private const RELATIONS = ['proprietaire.utilisateur', 'quartier.commune', 'equipements'];

    public function __construct(private readonly PerimetreGestionnaire $perimetre) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres, $request)->orderBy('nom')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ResidenceResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $residences = $this->requeteFiltree($filtres, $request)->orderBy('nom')->limit(5000)->get();

        $export = new ExportDeListe('Residences', [
            'nom' => 'Nom', 'proprietaire' => 'Propriétaire', 'lieu' => 'Lieu',
            'logements' => 'Logements', 'disponibilite' => 'Disponibilité', 'active' => 'Active',
        ], $residences->map(fn (Residence $r): array => [
            'nom' => $r->nom,
            'proprietaire' => $r->proprietaire->nomAffiche(),
            'lieu' => $r->quartier->commune->nom.' › '.$r->quartier->nom,
            'logements' => (string) $r->logements_count,
            'disponibilite' => $r->disponibilite->value === 'disponible' ? 'Disponible' : 'Occupée',
            'active' => $r->active ? 'Oui' : 'Non',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'proprietaire_id' => ['nullable', 'integer'],
            'commune_id' => ['nullable', 'integer'],
            'quartier_id' => ['nullable', 'integer'],
            'disponibilite' => ['nullable', 'in:disponible,occupee'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Residence>
     */
    private function requeteFiltree(array $filtres, Request $request): Builder
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $autorisees = $this->perimetre->residencesAutorisees($utilisateur);

        return Residence::query()
            ->with(self::RELATIONS)
            ->withCount('logements')
            ->when($autorisees !== null, fn (Builder $q) => $q->whereIn('id', $autorisees ?? []))
            ->when($filtres['recherche'] ?? null, fn (Builder $q, string $v) => $q->where('nom', 'ilike', '%'.addcslashes($v, '%_\\').'%'))
            ->when($filtres['proprietaire_id'] ?? null, fn (Builder $q, int $v) => $q->where('proprietaire_id', $v))
            ->when($filtres['quartier_id'] ?? null, fn (Builder $q, int $v) => $q->where('quartier_id', $v))
            ->when($filtres['commune_id'] ?? null, fn (Builder $q, int $v) => $q->whereRelation('quartier', 'commune_id', $v))
            ->when($filtres['disponibilite'] ?? null, fn (Builder $q, string $v) => $q->where('disponibilite', $v));
    }

    public function afficher(Residence $residence): JsonResponse
    {
        return ReponseApi::succes(new ResidenceResource(
            $residence->load([...self::RELATIONS, 'logements.type', 'logements.equipements'])->loadCount('logements'),
        ));
    }

    public function creer(ResidenceRequest $request): JsonResponse
    {
        $residence = $this->enregistrer(new Residence, $request->validated());

        // Celui qui onboarde une résidence continue de la gérer (CdC § 9.5) : sans ce
        // rattachement, elle lui serait invisible dès la requête suivante.
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        if ($utilisateur->profil === Profil::Gestionnaire) {
            $utilisateur->residences()->syncWithoutDetaching([$residence->id]);
        }

        return ReponseApi::cree(new ResidenceResource($residence), 'Résidence créée.');
    }

    public function modifier(ResidenceRequest $request, Residence $residence): JsonResponse
    {
        return ReponseApi::succes(
            new ResidenceResource($this->enregistrer($residence, $request->validated())),
            'Résidence modifiée.',
        );
    }

    public function supprimer(Residence $residence): JsonResponse
    {
        if ($residence->logements()->exists()) {
            throw new ErreurMetier(
                'Cette résidence contient des logements : elle ne peut pas être supprimée. Désactivez-la pour la retirer de la vente.',
                'residence_non_vide',
            );
        }

        $residence->delete();

        return ReponseApi::succes(null, 'Résidence supprimée.');
    }

    /** @param array<string, mixed> $saisie */
    private function enregistrer(Residence $residence, array $saisie): Residence
    {
        DB::transaction(function () use ($residence, $saisie): void {
            $residence->fill(Arr::except($saisie, 'equipements'))->save();

            if (array_key_exists('equipements', $saisie)) {
                $residence->equipements()->sync($saisie['equipements']);
            }
        });

        // refresh : relit les valeurs par défaut posées par la base (mode de vente, disponibilité).
        return $residence->refresh()->load(self::RELATIONS)->loadCount('logements');
    }
}
