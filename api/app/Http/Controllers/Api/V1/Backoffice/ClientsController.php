<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Client;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ClientResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Clients ordinaires : liste, fiche, bascules de TVA et liste noire (CdC § 5, P1-BO-07).
 * Les demandes de compte à terme, elles, restent instruites par `ComptesATermeController`.
 */
final class ClientsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ClientResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $clients = $this->requeteFiltree($filtres)->orderByDesc('id')->limit(5000)->get();

        $export = new ExportDeListe('Clients', [
            'nom' => 'Nom', 'email' => 'Courriel', 'telephone' => 'Téléphone',
            'statut' => 'Statut', 'nature' => 'Nature', 'liste_noire' => 'Liste noire', 'cree_le' => 'Créé le',
        ], $clients->map(fn (User $u): array => [
            'nom' => $u->nomComplet(),
            'email' => $u->email,
            'telephone' => $u->telephone ?? '',
            'statut' => $u->statut->libelle(),
            'nature' => $u->client ? (Client::NATURES[$u->client->nature] ?? $u->client->nature) : '',
            'liste_noire' => $u->client?->liste_noire ? 'Oui' : 'Non',
            'cree_le' => $u->created_at?->format('d/m/Y H:i:s') ?? '',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', Rule::enum(StatutCompte::class)],
            'liste_noire' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<User>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return User::query()
            ->where('profil', Profil::Client)
            ->with('client')
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $motif = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn (Builder $ou) => $ou
                    ->where('nom', 'ilike', $motif)->orWhere('prenoms', 'ilike', $motif)
                    ->orWhere('email', 'ilike', $motif)->orWhere('telephone', 'ilike', $motif));
            })
            ->when($filtres['statut'] ?? null, fn (Builder $q, string $v) => $q->where('statut', $v))
            ->when(array_key_exists('liste_noire', $filtres) && $filtres['liste_noire'] !== null,
                fn (Builder $q) => $q->whereHas('client', fn (Builder $c) => $c->where('liste_noire', (bool) $filtres['liste_noire'])));
    }

    public function afficher(User $client): JsonResponse
    {
        return ReponseApi::succes(new ClientResource($client->load('client')));
    }

    /**
     * Identité fiscale d'un client professionnel : raison sociale, NCC, RCCM (CdC § 9.4).
     * Sans ces champs, un client B2B/B2G/B2F ne peut jamais être facturé nommément à la DGI.
     */
    public function modifierLaFiche(Request $request, User $client): JsonResponse
    {
        $saisie = $request->validate([
            'raison_sociale' => ['sometimes', 'nullable', 'string', 'max:150'],
            'ncc' => ['sometimes', 'nullable', 'string', 'max:30'],
            'rccm' => ['sometimes', 'nullable', 'string', 'max:60'],
        ], [], ['raison_sociale' => 'raison sociale', 'ncc' => 'NCC', 'rccm' => 'RCCM']);

        Client::de($client)->update($saisie);

        return ReponseApi::succes(new ClientResource($client->refresh()->load('client')), 'Fiche mise à jour.');
    }

    /** Bascule de TVA (hébergement / transfert) : la réception peut la corriger à la demande du client, motif obligatoire (CdC § 9.4). */
    public function basculerLaTva(Request $request, User $client): JsonResponse
    {
        $saisie = $request->validate([
            'tva_hebergement' => ['sometimes', 'boolean'],
            'tva_transfert' => ['sometimes', 'boolean'],
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['motif' => 'motif']);

        if (! array_key_exists('tva_hebergement', $saisie) && ! array_key_exists('tva_transfert', $saisie)) {
            throw ValidationException::withMessages(['tva_hebergement' => 'Indiquez au moins une bascule à modifier.']);
        }

        /** @var User $administrateur */
        $administrateur = $request->user();
        $fiche = Client::de($client);
        $fiche->update([
            ...Arr::only($saisie, ['tva_hebergement', 'tva_transfert']),
            'tva_motif' => $saisie['motif'],
            'tva_motif_par' => $administrateur->id,
            'tva_motif_le' => now(),
        ]);

        return ReponseApi::succes(new ClientResource($client->refresh()->load('client')), 'Régime de TVA mis à jour.');
    }

    /** Ajouter/retirer un client de la liste noire : action réservée à un administrateur (CdC § 5). */
    public function basculerLaListeNoire(Request $request, User $client): JsonResponse
    {
        $administrateur = $request->user();
        if (! $administrateur instanceof User || ! $administrateur->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }

        $saisie = $request->validate([
            'en_liste_noire' => ['required', 'boolean'],
            'motif' => ['nullable', 'string', 'min:5', 'max:255', 'required_if:en_liste_noire,true'],
        ], [
            'motif.required_if' => 'Une mise en liste noire doit être motivée.',
        ]);

        $fiche = Client::de($client);
        $fiche->update($saisie['en_liste_noire']
            ? ['liste_noire' => true, 'liste_noire_motif' => $saisie['motif'], 'liste_noire_par' => $administrateur->id, 'liste_noire_le' => now()]
            : ['liste_noire' => false, 'liste_noire_motif' => null, 'liste_noire_par' => null, 'liste_noire_le' => null]);

        return ReponseApi::succes(
            new ClientResource($client->refresh()->load('client')),
            $saisie['en_liste_noire'] ? 'Client ajouté à la liste noire.' : 'Client retiré de la liste noire.',
        );
    }
}
