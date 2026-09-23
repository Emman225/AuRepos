<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réduction sur un séjour (CdC § 6.1) : « un administrateur saisit un pourcentage, le
 * trésorier (validant 2) confirme ; la remise se calcule sur le hors taxes. » Passe par
 * la double validation générique (`changements_a_valider`, champ `reduction_pourcentage`),
 * avec une contrainte SUPPLÉMENTAIRE posée par le service : seul le trésorier désigné
 * confirme, pas n'importe quel administrateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sejours', function (Blueprint $table): void {
            $table->decimal('reduction_pourcentage', 5, 2)->nullable()->after('net_a_payer');
            $table->string('reduction_motif')->nullable()->after('reduction_pourcentage');
        });

        DB::statement('ALTER TABLE sejours ADD CONSTRAINT sejours_reduction_bornee CHECK (reduction_pourcentage IS NULL OR (reduction_pourcentage >= 0 AND reduction_pourcentage <= 100))');
    }

    public function down(): void
    {
        Schema::table('sejours', function (Blueprint $table): void {
            $table->dropColumn(['reduction_pourcentage', 'reduction_motif']);
        });
    }
};
