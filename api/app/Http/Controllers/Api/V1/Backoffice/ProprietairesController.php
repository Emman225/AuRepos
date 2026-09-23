<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Services\CreationDePartenaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partenaires\ProprietaireRequest;
use App\Http\Resources\Backoffice\ProprietaireResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Propriétaires › Liste des propriétaires (CdC § 7.2). */
final class ProprietairesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        // Le compte interne de l'entreprise en tête, avec sa marque distinctive.
        $page = $this->requeteFiltree($filtres)->orderByDesc('interne')->orderBy('id')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ProprietaireResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $proprietaires = $this->requeteFiltree($filtres)->orderByDesc('interne')->orderBy('id')->limit(5000)->get();

        $export = new ExportDeListe('Proprietaires', [
            'nom' => 'Nom', 'nature' => 'Nature', 'email' => 'Courriel', 'telephone' => 'Téléphone',
            'residences' => 'Résidences', 'dossier' => 'Dossier',
        ], $proprietaires->map(fn (Proprietaire $p): array => [
            'nom' => $p->nomAffiche(),
            'nature' => $p->nature->libelle(),
            'email' => $p->utilisateur->email,
            'telephone' => $p->utilisateur->telephone ?? '',
            'residences' => (string) $p->residences_count,
            'dossier' => $p->elementsManquants() === [] ? 'Complet' : 'Incomplet',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'interne' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Proprietaire>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return Proprietaire::query()
            ->with(['utilisateur', 'pieces'])
            ->withCount('residences')
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $motif = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn (Builder $ou) => $ou
                    ->where('raison_sociale', 'ilike', $motif)
                    ->orWhereHas('utilisateur', fn (Builder $u) => $u
                        ->where('nom', 'ilike', $motif)->orWhere('prenoms', 'ilike', $motif)->orWhere('email', 'ilike', $motif)));
            })
            ->when(array_key_exists('interne', $filtres) && $filtres['interne'] !== null,
                fn (Builder $q) => $q->where('interne', (bool) $filtres['interne']));
    }

    public function afficher(Proprietaire $proprietaire): JsonResponse
    {
        return ReponseApi::succes(new ProprietaireResource($this->complet($proprietaire)));
    }

    public function creer(ProprietaireRequest $request, CreationDePartenaire $creation): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();
        $this->reserverLeCompteInterne($request, $fiche);

        $proprietaire = $creation->proprietaire($compte, $fiche);

        return ReponseApi::cree(
            new ProprietaireResource($this->complet($proprietaire)),
            'Propriétaire créé. Un courriel l’invite à choisir son mot de passe.',
        );
    }

    public function modifier(ProprietaireRequest $request, Proprietaire $proprietaire): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();
        $this->reserverLeCompteInterne($request, $fiche);

        DB::transaction(function () use ($proprietaire, $compte, $fiche): void {
            $proprietaire->utilisateur->update($compte);
            $proprietaire->update($fiche);
        });

        return ReponseApi::succes(new ProprietaireResource($this->complet($proprietaire->refresh())), 'Propriétaire modifié.');
    }

    private function complet(Proprietaire $proprietaire): Proprietaire
    {
        return $proprietaire->load(['utilisateur', 'pieces'])->loadCount('residences');
    }

    /**
     * Marquer un compte « interne » le dispense de retenue à la source : seul un administrateur le peut.
     *
     * @param  array<string, mixed>  $fiche
     */
    private function reserverLeCompteInterne(Request $request, array $fiche): void
    {
        $utilisateur = $request->user();

        if (array_key_exists('interne', $fiche) && ! ($utilisateur instanceof User && $utilisateur->profil->estAdministrateur())) {
            throw new AuthorizationException;
        }
    }
}
