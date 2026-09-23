<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Tarification des séjours (tâches P1-TAR-01 à 03, CdC § 7.3).
|
| « Pour tel type, en telle saison, pour telle durée, la nuit coûte tant » — même
| structure que le barème de transport de Mon Gravier.
|
| Ordre de lecture du tarif d'une nuit, du plus précis au plus général :
|   1. ligne de grille propre au LOGEMENT ;
|   2. ligne de grille de son TYPE ;
|   3. à défaut, le prix de vente du logement (validé en double validation).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saisons', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100);
            // basse / haute : se suivent sans trou ni chevauchement. evenement : se pose PAR-DESSUS
            // (fêtes, CAN, salon…) et l'emporte sur la saison qu'il recouvre.
            $table->string('categorie', 20);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE saisons ADD CONSTRAINT saisons_categorie_connue CHECK (categorie IN ('basse','haute','evenement'))");
        DB::statement('ALTER TABLE saisons ADD CONSTRAINT saisons_dates_coherentes CHECK (date_fin >= date_debut)');

        Schema::create('tranches_duree', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100);
            $table->unsignedSmallInteger('nuits_min');
            // Nul = « et plus » (ex. 30 nuits et plus).
            $table->unsignedSmallInteger('nuits_max')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE tranches_duree ADD CONSTRAINT tranches_bornes_coherentes CHECK (nuits_min >= 1 AND (nuits_max IS NULL OR nuits_max >= nuits_min))');

        Schema::create('grille_tarifaire', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_logement_id')->nullable()->constrained('types_logement')->restrictOnDelete();
            $table->foreignId('logement_id')->nullable()->constrained('logements')->cascadeOnDelete();
            $table->foreignId('saison_id')->constrained('saisons')->cascadeOnDelete();
            $table->foreignId('tranche_duree_id')->constrained('tranches_duree')->cascadeOnDelete();
            $table->unsignedBigInteger('tarif');
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        // Une ligne vise un type OU un logement, jamais les deux ni aucun.
        DB::statement('ALTER TABLE grille_tarifaire ADD CONSTRAINT grille_une_seule_cible CHECK ((type_logement_id IS NULL) <> (logement_id IS NULL))');
        DB::statement('CREATE UNIQUE INDEX grille_unique_par_type ON grille_tarifaire (type_logement_id, saison_id, tranche_duree_id) WHERE type_logement_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX grille_unique_par_logement ON grille_tarifaire (logement_id, saison_id, tranche_duree_id) WHERE logement_id IS NOT NULL');

        Schema::create('supplements', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40);
            $table->string('nom', 100);
            // par_nuit_et_par_personne · par_nuit · forfait
            $table->string('mode', 30);
            $table->unsignedBigInteger('montant');
            // Nul = vaut pour tous les types de logement.
            $table->foreignId('type_logement_id')->nullable()->constrained('types_logement')->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE supplements ADD CONSTRAINT supplements_code_connu CHECK (code IN ('occupant_supplementaire','week_end','arrivee_tardive','depart_tardif'))");
        DB::statement("ALTER TABLE supplements ADD CONSTRAINT supplements_mode_connu CHECK (mode IN ('par_nuit_et_par_personne','par_nuit','forfait'))");
        DB::statement('CREATE UNIQUE INDEX supplements_unique ON supplements (code, COALESCE(type_logement_id, 0))');
    }

    public function down(): void
    {
        foreach (['supplements', 'grille_tarifaire', 'tranches_duree', 'saisons'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
