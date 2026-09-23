<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dérogation du pourcentage entreprise par logement (CdC § 7.3) : « pourcentage entreprise…
 * dérogation par logement possible, même double validation, bandeau permanent listant les
 * dérogations. » Null = pas de dérogation, le taux global des Paramètres s'applique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table): void {
            $table->decimal('pourcentage_entreprise_derogation', 5, 2)->nullable()->after('prix_proprietaire');
        });

        DB::statement('ALTER TABLE logements ADD CONSTRAINT logements_derogation_positive CHECK (pourcentage_entreprise_derogation IS NULL OR pourcentage_entreprise_derogation >= 0)');
    }

    public function down(): void
    {
        Schema::table('logements', function (Blueprint $table): void {
            $table->dropColumn('pourcentage_entreprise_derogation');
        });
    }
};
