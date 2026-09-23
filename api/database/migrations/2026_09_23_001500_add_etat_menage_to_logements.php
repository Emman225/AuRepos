<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| État de propreté du logement (P2-MEN-02, CdC § 6.4) : sale → en cours → propre →
| contrôlé (= « prêt »). Nullable : un logement neuf ou jamais suivi par le ménage n'a
| pas encore d'état — on ne lui invente pas un « sale » ou « propre » par défaut.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table): void {
            $table->string('etat_menage', 20)->nullable()->after('etat_publication');
        });

        DB::statement("ALTER TABLE logements ADD CONSTRAINT logements_etat_menage_connu CHECK (etat_menage IN ('sale','en_cours','propre','controle'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE logements DROP CONSTRAINT logements_etat_menage_connu');
        Schema::table('logements', function (Blueprint $table): void {
            $table->dropColumn('etat_menage');
        });
    }
};
