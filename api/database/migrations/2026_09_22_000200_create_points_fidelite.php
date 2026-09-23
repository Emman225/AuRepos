<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Points de fidélité (tâche P1-CAI-06, CdC § 4) :
| « 1 000 F encaissés = 1 point, 1 point = 10 F (paramétrable) ; résultat tronqué, points repris
| à l'annulation remboursée, minimum à payer pour qu'un séjour ne tombe jamais à 0 F. »
|
| Aucun solde stocké : le solde est la SOMME des mouvements. Un total dérivé ne peut pas
| se désynchroniser de son détail, et le relevé du client s'explique toujours ligne par ligne.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            // acquisition (+) · utilisation (−) · reprise (−, à l'annulation remboursée) · restitution (+)
            $table->string('nature', 20);
            $table->integer('points');
            // Ce qui l'a produit : le règlement encaissé, ou le séjour réservé.
            $table->nullableMorphs('origine');
            $table->string('libelle');
            // Figés : le barème d'aujourd'hui ne doit pas réécrire l'histoire d'hier.
            $table->unsignedBigInteger('montant_par_point');
            $table->unsignedBigInteger('valeur_du_point');
            $table->timestamps();

            $table->index(['client_id', 'id']);
        });

        DB::statement("ALTER TABLE mouvements_points ADD CONSTRAINT points_nature_connue CHECK (nature IN ('acquisition','utilisation','reprise','restitution'))");
        DB::statement("ALTER TABLE mouvements_points ADD CONSTRAINT points_sens_coherent CHECK (
            (nature IN ('acquisition','restitution') AND points > 0) OR (nature IN ('utilisation','reprise') AND points < 0)
        )");

        Schema::table('sejours', function (Blueprint $table): void {
            // Points demandés par le client à la réservation, et leur valeur en francs, figée.
            $table->unsignedInteger('points_utilises')->default(0)->after('caution');
            $table->unsignedBigInteger('reduction_points')->default(0)->after('points_utilises');
        });
    }

    public function down(): void
    {
        Schema::table('sejours', fn (Blueprint $table) => $table->dropColumn(['points_utilises', 'reduction_points']));
        Schema::dropIfExists('mouvements_points');
    }
};
