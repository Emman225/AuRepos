<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\Agence;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\AgenceResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Agences : le guichet d'encaissement auquel un administrateur ou un gestionnaire est rattaché
 * (CdC § 8.1, 9.5). Un compte sans agence ne peut pas encaisser (`User::peutEncaisser()`).
 */
final class AgencesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderBy('nom')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, AgenceResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $agences = $this->requeteFiltree($filtres)->orderBy('nom')->limit(5000)->get();

        $export = new ExportDeListe('Agences', [
            'nom' => 'Nom', 'adresse' => 'Adresse', 'telephone' => 'Téléphone',
            'utilisateurs' => 'Utilisateurs', 'active' => 'Active',
        ], $agences->map(fn (Agence $a): array => [
            'nom' => $a->nom,
            'adresse' => $a->adresse ?? '',
            'telephone' => $a->telephone ?? '',
            'utilisateurs' => (string) $a->utilisateurs_count,
            'active' => $a->active ? 'Oui' : 'Non',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Agence>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return Agence::query()->withCount('utilisateurs')
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $q->where('nom', 'ilike', '%'.addcslashes($v, '%_\\').'%');
            })
            ->when(array_key_exists('active', $filtres) && $filtres['active'] !== null,
                fn (Builder $q) => $q->where('active', (bool) $filtres['active']));
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $this->valider($request);

        $agence = Agence::create($saisie);

        return ReponseApi::cree(new AgenceResource($agence), 'Agence créée.');
    }

    public function modifier(Request $request, Agence $agence): JsonResponse
    {
        $saisie = $this->valider($request, $agence);

        $agence->update($saisie);

        return ReponseApi::succes(new AgenceResource($agence->refresh()), 'Agence modifiée.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?Agence $agence = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('agences', 'nom')->ignore($agence?->id)],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/'],
            'active' => ['sometimes', 'boolean'],
        ], [], ['nom' => 'nom', 'adresse' => 'adresse', 'telephone' => 'téléphone', 'active' => 'actif']);
    }
}
