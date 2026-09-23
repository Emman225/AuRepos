<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Services\RegistreDesReferentiels;
use App\Http\Controllers\Controller;
use App\Support\Api\ErreurMetier;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Un seul contrôleur pour tous les référentiels, décrit par le registre.
 *   - lecture publique : listes de choix (éléments actifs seulement), en cascade ;
 *   - gestion au back office : liste complète, création, modification, suppression.
 */
final class ReferentielsController extends Controller
{
    public function __construct(private readonly RegistreDesReferentiels $registre) {}

    /** Listes de choix du site et des applications : commune › quartier, types, équipements. */
    public function choix(Request $request, string $slug): JsonResponse
    {
        $definition = $this->registre->definition($slug);
        if (! $definition['public']) {
            throw new NotFoundHttpException;
        }

        $elements = $this->filtrerParParent($definition['modele']::query(), $definition, $request)
            ->where($definition['actif'], true)
            ->orderBy($definition['tri'][0])->orderBy('id')
            ->get()
            ->map(fn (Model $e): array => $this->presenter($e, $definition));

        return ReponseApi::succes($elements);
    }

    public function index(Request $request, string $slug): JsonResponse
    {
        $definition = $this->definitionGerable($request, $slug);
        $requete = $this->requeteFiltree($request, $definition);

        $page = $requete->orderBy('id')->paginate(min(100, max(5, (int) $request->query('par_page', 25))));

        return ReponseApi::succes([
            'elements' => collect($page->items())->map(fn (Model $e): array => $this->presenter($e, $definition)),
            'pagination' => [
                'page' => $page->currentPage(), 'par_page' => $page->perPage(),
                'total' => $page->total(), 'derniere_page' => $page->lastPage(),
            ],
        ]);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8) — générique, comme le reste du contrôleur. */
    public function exporter(Request $request, string $slug): Response
    {
        $definition = $this->definitionGerable($request, $slug);
        $filtres = $request->validate(['format' => ['required', Rule::in(['xlsx', 'docx', 'pdf'])]]);

        $elements = $this->requeteFiltree($request, $definition)->orderBy('id')->limit(5000)->get();

        $libelles = $this->registre->libelles();
        $colonnes = [];
        foreach ($definition['champs'] as $champ) {
            $colonnes[$champ] = $libelles[$champ] ?? ucfirst(str_replace('_', ' ', $champ));
        }

        $lignes = $elements->map(function (Model $e) use ($definition, $colonnes): array {
            $presente = $this->presenter($e, $definition);
            $ligne = [];
            foreach (array_keys($colonnes) as $champ) {
                $valeur = $presente[$champ] ?? '';
                $ligne[$champ] = is_bool($valeur) ? ($valeur ? 'Oui' : 'Non') : (string) $valeur;
            }

            return $ligne;
        })->all();

        $export = new ExportDeListe($definition['libelle'], $colonnes, $lignes);

        return $export->reponse($filtres['format']);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return Builder<Model>
     */
    private function requeteFiltree(Request $request, array $definition): Builder
    {
        $recherche = trim((string) $request->query('recherche', ''));

        $requete = $this->filtrerParParent($definition['modele']::query(), $definition, $request)
            ->when(isset($definition['parent']), fn (Builder $q) => $q->with($definition['parent']['relation']))
            ->when($recherche !== '', function (Builder $q) use ($definition, $recherche): void {
                $motif = '%'.addcslashes($recherche, '%_\\').'%';
                $q->where(function (Builder $ou) use ($definition, $motif): void {
                    foreach ($definition['recherche'] as $colonne) {
                        $ou->orWhere($colonne, 'ilike', $motif);
                    }
                });
            });

        foreach ($definition['tri'] as $colonne) {
            $requete->orderBy($colonne);
        }

        return $requete;
    }

    public function creer(Request $request, string $slug): JsonResponse
    {
        $definition = $this->definitionGerable($request, $slug);
        $element = $definition['modele']::create($this->valider($request, $slug));

        return ReponseApi::cree($this->presenter($element, $definition), 'Élément ajouté.');
    }

    public function modifier(Request $request, string $slug, int $id): JsonResponse
    {
        $definition = $this->definitionGerable($request, $slug);
        $element = $definition['modele']::findOrFail($id);
        $element->update($this->valider($request, $slug, $element));

        return ReponseApi::succes($this->presenter($element, $definition), 'Élément modifié.');
    }

    public function supprimer(Request $request, string $slug, int $id): JsonResponse
    {
        $definition = $this->definitionGerable($request, $slug);
        $element = $definition['modele']::findOrFail($id);

        try {
            // Point de sauvegarde : un refus de la base n'annule pas la transaction en cours.
            DB::transaction(fn () => $element->delete());
        } catch (QueryException $e) {
            if ($e->getCode() === '23503' || $e->getCode() === '23001') {
                throw new ErreurMetier(
                    'Cet élément est déjà utilisé : il ne peut pas être supprimé. Désactivez-le pour le retirer des listes de choix.',
                    'element_utilise',
                );
            }
            throw $e;
        }

        return ReponseApi::succes(null, 'Élément supprimé.');
    }

    /**
     * Agences (guichets d'encaissement) et statuts métier : administrateurs seulement,
     * même si la route laisse entrer les gestionnaires pour les autres référentiels.
     *
     * @return array<string, mixed>
     */
    private function definitionGerable(Request $request, string $slug): array
    {
        $definition = $this->registre->definition($slug);
        $utilisateur = $request->user();

        if (($definition['reserve_administrateurs'] ?? false) && ! ($utilisateur instanceof User && $utilisateur->profil->estAdministrateur())) {
            throw new AuthorizationException;
        }

        return $definition;
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, string $slug, ?Model $existant = null): array
    {
        $definition = $this->registre->definition($slug);
        // L'unicité d'un nom s'apprécie DANS son parent : deux communes peuvent avoir un quartier « Centre ».
        $champParent = $definition['parent']['champ'] ?? ($slug === 'statuts-metier' ? 'domaine' : null);
        $parentId = $champParent ? ($request->input($champParent) ?? $existant?->getAttribute($champParent)) : null;

        $regles = $this->registre->regles($slug, $existant?->getKey(), $parentId);
        if ($existant !== null) {
            // Modification partielle : seuls les champs envoyés sont contrôlés et changés.
            $regles = array_intersect_key($regles, $request->all());
        }

        // Les règles croisées (fin ≥ début, maximum ≥ minimum) doivent voir les valeurs déjà
        // enregistrées quand un seul des deux champs est envoyé.
        $donnees = $request->all();
        if ($existant !== null) {
            $actuelles = array_map(
                fn (mixed $v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d') : $v,
                $existant->only($definition['champs']),
            );
            $donnees = [...$actuelles, ...$donnees];
        }

        return Validator::make($donnees, $regles, [
            'regex' => 'Le champ :attribute n’a pas le bon format.',
        ], $this->registre->libelles())->validate();
    }

    /**
     * @param  Builder<Model>  $requete
     * @param  array<string, mixed>  $definition
     * @return Builder<Model>
     */
    private function filtrerParParent(Builder $requete, array $definition, Request $request): Builder
    {
        $champ = $definition['parent']['champ'] ?? null;

        return $champ && $request->filled($champ)
            ? $requete->where($champ, (int) $request->query($champ))
            : $requete;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function presenter(Model $element, array $definition): array
    {
        $donnees = ['id' => $element->getKey()];
        foreach ($definition['champs'] as $champ) {
            $valeur = $element->getAttribute($champ);
            $donnees[$champ] = $valeur instanceof DateTimeInterface ? $valeur->format('Y-m-d') : $valeur;
        }
        if (isset($definition['parent']) && $element->relationLoaded($definition['parent']['relation'])) {
            $donnees['parent'] = $element->getRelation($definition['parent']['relation'])?->getAttribute('nom');
        }

        return $donnees;
    }
}
