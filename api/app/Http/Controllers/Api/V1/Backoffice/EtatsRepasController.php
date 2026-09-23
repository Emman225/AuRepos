<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * États « Repas et boissons » (P4-API-09) : activité (par période / restaurateur / état) et
 * marge par commande. Lecture seule, aucun état P3-ETA/P3-CPT n'étant encore construit pour
 * en reprendre le patron — agrégat simple, même filtre « Période » que partout ailleurs
 * (`App\Support\Listes\FiltrePeriode`, CdC § 6.8).
 *
 * CA et marge ne comptent QUE les commandes LIVRÉES — même périmètre que
 * `App\Domain\Repas\Services\GestionDesCommandes::detteEnversLeRestaurateur`, qui ne doit
 * rien à un restaurateur sur une commande encore réversible (prête, en livraison…). La
 * quantité retenue par ligne est la SERVIE une fois connue, sinon la commandée — même règle
 * que la dette. Une commande OFFERTE (extra « premier repas à l'arrivée », P4-API-08) est
 * incluse dans le décompte mais à zéro franc de vente : elle apparaît donc en marge
 * négative, exactement son coût réel pour l'établissement, jamais gonflée dans le CA.
 */
final class EtatsRepasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'restaurateur_id' => ['nullable', 'integer', 'exists:restaurateurs,id'],
            'etat' => ['nullable', Rule::enum(EtatDeCommande::class)],
        ]);

        $requete = Commande::query()
            ->with(['restaurateur.utilisateur', 'lignes.produit'])
            ->when($filtres['restaurateur_id'] ?? null, fn (Builder $q, $v) => $q->where('restaurateur_id', $v))
            ->when($filtres['etat'] ?? null, fn (Builder $q, $v) => $q->where('etat', $v));

        $commandes = FiltrePeriode::depuis($request)->appliquer($requete)->orderByDesc('id')->get();

        $detail = $commandes->where('etat', EtatDeCommande::Livree)->map(function (Commande $commande): array {
            $ventes = 0;
            $cout = 0;
            foreach ($commande->lignes as $ligne) {
                $quantite = $ligne->quantite_servie ?? $ligne->quantite_commandee;
                $ventes += $ligne->prix_unitaire_vente * $quantite;
                $cout += ($ligne->produit?->prix_restaurateur ?? 0) * $quantite;
            }

            return [
                'commande_id' => $commande->id,
                'reference' => $commande->reference,
                'restaurateur' => $commande->restaurateur?->nomAffiche(),
                'offert' => $commande->offert,
                'ventes' => $ventes,
                'cout_restaurateur' => $cout,
                'marge' => $ventes - $cout,
            ];
        })->values();

        return ReponseApi::succes([
            'nombre_de_commandes' => $commandes->count(),
            'par_etat' => (object) $commandes->countBy(fn (Commande $c) => $c->etat->value)->all(),
            'nombre_de_repas_offerts' => $commandes->where('offert', true)->count(),
            'ca_repas' => (int) $detail->sum('ventes'),
            'cout_restaurateurs' => (int) $detail->sum('cout_restaurateur'),
            'marge_totale' => (int) $detail->sum('marge'),
            'commandes' => $detail,
        ]);
    }
}
