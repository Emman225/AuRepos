<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Barème de rémunération du ménage (P2-MEN-04, CdC § 6.4) : « forfait par type de
| logement… plancher par mission ». Un seul forfait par type de logement — même patron
| que `baremes_livraison_repas` (un seul forfait par résidence) — le type de logement
| JOUE le rôle de la « grille propre » : un appartement 3 pièces et un studio ont
| chacun leur ligne, donc leur propre montant, sans mécanisme de grille séparé à inventer.
|
| `plancher` est le montant minimum versé pour une mission de ce type de logement, même
| si une règle future réduisait le forfait (aucune réduction n'existe aujourd'hui : le
| plancher ne joue donc pour l'instant aucun rôle de correction, il est simplement acquis
| dès la résolution du barème — construit maintenant pour ne pas re-migrer plus tard).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baremes_menage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_logement_id')->unique()->constrained('types_logement')->restrictOnDelete();
            $table->unsignedInteger('forfait');
            $table->unsignedInteger('plancher');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE baremes_menage ADD CONSTRAINT baremes_menage_plancher_coherent CHECK (plancher <= forfait)');
    }

    public function down(): void
    {
        Schema::dropIfExists('baremes_menage');
    }
};
