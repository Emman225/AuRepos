<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client ordinaire / client à terme (CdC § 5.1, 5.3) : une ligne de crédit pour les
 * organisations (entreprise, ONG, administration, ambassade), instruite comme un dossier
 * — RCCM, bilan et pièce d'identité, via les mêmes pièces justificatives que les propriétaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('statut_a_terme', 15)->default('aucune')->after('code_exoneration');
            // 0 = « aucune limite » (CdC § 5.1) ; n'a de sens qu'une fois le compte accepté.
            $table->unsignedBigInteger('plafond_credit')->default(0)->after('statut_a_terme');
            $table->timestamp('demande_a_terme_le')->nullable()->after('plafond_credit');
            $table->string('a_terme_motif_refus')->nullable()->after('demande_a_terme_le');
            $table->foreignId('a_terme_traite_par')->nullable()->after('a_terme_motif_refus')->constrained('users')->nullOnDelete();
            $table->timestamp('a_terme_traite_le')->nullable()->after('a_terme_traite_par');
        });

        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_statut_a_terme_connu CHECK (statut_a_terme IN ('aucune','en_attente','acceptee','refusee'))");
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('a_terme_traite_par');
            $table->dropColumn(['statut_a_terme', 'plafond_credit', 'demande_a_terme_le', 'a_terme_motif_refus', 'a_terme_traite_le']);
        });
    }
};
