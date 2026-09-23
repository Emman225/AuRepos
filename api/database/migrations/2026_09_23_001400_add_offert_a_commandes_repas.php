<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Extra CdC § 5.2 « premier repas livré à l'arrivée » (P4-API-08) : représenté comme une
| commande de repas ordinaire (mêmes états, même circuit de préparation/livraison), mais
| offerte au client — `offert` la distingue pour les états (P4-API-09, jamais comptée dans
| le CA) sans toucher à la dette du restaurateur, qui reste due à son prix normal
| (App\Domain\Repas\Services\GestionDesCommandes::detteEnversLeRestaurateur lit
| `produit->prix_restaurateur`, jamais `montant_total` ni `prix_unitaire_vente`).
|
| Le catalogue général des extras choisis à la réservation (P2-EXT-01) n'est pas construit :
| ce geste-ci se déclenche explicitement depuis la réception, après le check-in, plutôt que
| d'inventer une règle de sélection automatique du restaurateur ou des plats offerts.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes_repas', function (Blueprint $table): void {
            $table->boolean('offert')->default(false)->after('montant_total');
        });
    }

    public function down(): void
    {
        Schema::table('commandes_repas', function (Blueprint $table): void {
            $table->dropColumn('offert');
        });
    }
};
