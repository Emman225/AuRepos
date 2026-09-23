<?php

namespace App\Http\Controllers\Api\V1\Restaurateur;

use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Http\Controllers\Api\V1\Restaurateur\Concerns\ResoutLeRestaurateurConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tableau de bord du restaurateur : ses commandes par état, sa dette. */
final class TableauDeBordController extends Controller
{
    use ResoutLeRestaurateurConnecte;

    public function index(Request $request, GestionDesCommandes $commandes): JsonResponse
    {
        $restaurateur = $this->monRestaurateur($request);

        $parEtat = Commande::query()->where('restaurateur_id', $restaurateur->id)->get('etat')->countBy(fn (Commande $c) => $c->etat->value);

        return ReponseApi::succes([
            'commandes_par_etat' => collect(EtatDeCommande::cases())
                ->mapWithKeys(fn (EtatDeCommande $e) => [$e->value => (int) ($parEtat[$e->value] ?? 0)]),
            'dette' => $commandes->detteEnversLeRestaurateur($restaurateur),
        ]);
    }
}
