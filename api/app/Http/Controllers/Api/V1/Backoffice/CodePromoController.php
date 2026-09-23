<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Tarification\Models\CodePromo;
use App\Domain\Tarification\Services\CodesPromo;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\CodePromoResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Codes promo : réduction en pourcentage ou en montant, période de validité, activable par résidence (CdC § 7.3). */
final class CodePromoController extends Controller
{
    public function __construct(private readonly CodesPromo $codesPromo) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, CodePromoResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $codes = $this->requeteFiltree($filtres)->orderByDesc('id')->limit(5000)->get();

        $export = new ExportDeListe('Codes promo', [
            'code' => 'Code', 'type' => 'Type', 'valeur' => 'Valeur', 'residence' => 'Résidence',
            'date_debut' => 'Début', 'date_fin' => 'Fin', 'actif' => 'Actif',
        ], $codes->map(fn (CodePromo $c): array => [
            'code' => $c->code,
            'type' => $c->type,
            'valeur' => (string) $c->valeur,
            'residence' => $c->residence ? $c->residence->nom : 'Toutes',
            'date_debut' => $c->date_debut->format('d/m/Y'),
            'date_fin' => $c->date_fin->format('d/m/Y'),
            'actif' => $c->actif ? 'Oui' : 'Non',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'actif' => ['nullable', 'boolean'],
            'residence_id' => ['nullable', 'integer'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<CodePromo>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return CodePromo::query()->with('residence')
            ->when(array_key_exists('actif', $filtres) && $filtres['actif'] !== null, fn (Builder $q) => $q->where('actif', (bool) $filtres['actif']))
            ->when($filtres['residence_id'] ?? null, fn (Builder $q, int $v) => $q->where('residence_id', $v));
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $this->valider($request);

        /** @var User $auteur */
        $auteur = $request->user();
        $codePromo = $this->codesPromo->creer(
            $saisie['code'], $saisie['type'], (int) $saisie['valeur'],
            Carbon::parse($saisie['date_debut']), Carbon::parse($saisie['date_fin']),
            $saisie['residence_id'] ?? null, $saisie['description'] ?? null, $auteur,
        );

        return ReponseApi::cree(new CodePromoResource($codePromo->load('residence')), 'Code promo créé.');
    }

    public function modifier(Request $request, CodePromo $codePromo): JsonResponse
    {
        $saisie = $request->validate([
            'actif' => ['nullable', 'boolean'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['actif' => 'actif', 'date_fin' => 'date de fin', 'description' => 'description']);

        $codePromo = $this->codesPromo->modifier($codePromo, $saisie);

        return ReponseApi::succes(new CodePromoResource($codePromo->load('residence')), 'Code promo modifié.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('codes_promo', 'code')],
            'type' => ['required', Rule::in(['pourcentage', 'montant'])],
            'valeur' => ['required', 'integer', 'min:1', Rule::when(fn (): bool => $request->input('type') === 'pourcentage', ['max:100'])],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'residence_id' => ['nullable', 'integer', Rule::exists('residences', 'id')],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], [
            'code' => 'code', 'type' => 'type', 'valeur' => 'valeur', 'date_debut' => 'date de début',
            'date_fin' => 'date de fin', 'residence_id' => 'résidence', 'description' => 'description',
        ]);
    }
}
