<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Écran unique « Paramètres », organisé en onglets (CdC § 12). Réservé aux administrateurs. */
final class ParametresController extends Controller
{
    public function __construct(private readonly Parametres $parametres) {}

    public function index(): JsonResponse
    {
        return ReponseApi::succes([
            'onglets' => $this->parametres->onglets(),
            // Pour les listes déroulantes « validant 1 / 2 » et « validateur des publications ».
            'administrateurs' => User::query()
                ->whereIn('profil', [Profil::SuperAdministrateur, Profil::Administrateur])
                ->where('statut', StatutCompte::Actif)
                ->orderBy('nom')
                ->get()
                ->map(fn (User $u): array => ['id' => $u->id, 'nom' => $u->nomComplet()]),
            // À afficher en rouge tant qu'il manque (CdC § 6.1).
            'alertes' => array_values(array_filter([
                $this->parametres->tresorierDesigne() ? null : 'Aucun « Gestionnaire validant 2 » (trésorier) n’est désigné : aucune réduction ni geste commercial ne peut être confirmé.',
                $this->parametres->valeur('entreprise.ncc') ? null : 'Le NCC de l’entreprise n’est pas renseigné : il est indispensable avant la première facture.',
            ])),
        ]);
    }

    public function enregistrer(Request $request, string $onglet): JsonResponse
    {
        /** @var User $auteur */
        $auteur = $request->user();
        $saisie = $request->validate(['valeurs' => ['required', 'array']])['valeurs'];

        $this->parametres->enregistrer($onglet, $saisie, $auteur);

        return ReponseApi::succes(['onglets' => $this->parametres->onglets()], 'Paramètres enregistrés.');
    }
}
