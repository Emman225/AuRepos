<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Contenu\Models\AbonneNewsletter;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\AbonneNewsletterResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Abonnés à la lettre d'information (CdC § 12, écran Paramètres › Divers, P1-BO-10). L'inscription
 * se fait en libre-service (route publique) ; ici, consultation et désabonnement manuel seulement.
 */
final class NewsletterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderByDesc('abonne_le')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, AbonneNewsletterResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $abonnes = $this->requeteFiltree($filtres)->orderByDesc('abonne_le')->limit(5000)->get();

        $export = new ExportDeListe('Newsletter', [
            'email' => 'Courriel', 'actif' => 'Actif', 'abonne_le' => 'Abonné le',
        ], $abonnes->map(fn (AbonneNewsletter $a): array => [
            'email' => $a->email,
            'actif' => $a->actif ? 'Oui' : 'Non',
            'abonne_le' => $a->abonne_le->format('d/m/Y'),
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'actif' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<AbonneNewsletter>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return AbonneNewsletter::query()
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $q->where('email', 'ilike', '%'.addcslashes($v, '%_\\').'%');
            })
            ->when(array_key_exists('actif', $filtres) && $filtres['actif'] !== null,
                fn (Builder $q) => $q->where('actif', (bool) $filtres['actif']));
    }

    public function desactiver(AbonneNewsletter $abonne): JsonResponse
    {
        $abonne->update(['actif' => false, 'desabonne_le' => now()]);

        return ReponseApi::succes(new AbonneNewsletterResource($abonne), 'Abonné désinscrit.');
    }
}
