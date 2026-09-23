<?php

namespace App\Http\Controllers\Api\V1\Livreur;

use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Http\Controllers\Api\V1\Livreur\Concerns\ResoutLeLivreurConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tableau de bord du livreur : courses en cours, gains (CdC — espace livreur). */
final class TableauDeBordController extends Controller
{
    use ResoutLeLivreurConnecte;

    public function index(Request $request, GestionDesCommandes $commandes): JsonResponse
    {
        $livreur = $this->monLivreur($request);

        return ReponseApi::succes([
            'courses_en_cours' => Commande::query()->where('livreur_id', $livreur->id)->where('etat', EtatDeCommande::EnLivraison)->count(),
            'courses_livrees' => Commande::query()->where('livreur_id', $livreur->id)->where('etat', EtatDeCommande::Livree)->count(),
            'gains' => $commandes->gainsDuLivreur($livreur),
        ]);
    }
}
