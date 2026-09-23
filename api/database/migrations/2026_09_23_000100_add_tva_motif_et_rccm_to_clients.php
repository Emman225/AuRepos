<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-FNE-05 (CdC § 9.4) : toute bascule de TVA doit être motivée, comme la liste noire l'est déjà
 * (`liste_noire_motif`). P1-FNE-03 : le RCCM d'un client professionnel (B2B/B2G/B2F), absent
 * jusqu'ici — seuls le RCCM du propriétaire et celui de l'entreprise existaient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('rccm', 60)->nullable()->after('ncc');
            $table->string('tva_motif', 255)->nullable()->after('liste_noire_le');
            $table->foreignId('tva_motif_par')->nullable()->after('tva_motif')->constrained('users')->nullOnDelete();
            $table->timestamp('tva_motif_le')->nullable()->after('tva_motif_par');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tva_motif_par');
            $table->dropColumn(['rccm', 'tva_motif', 'tva_motif_le']);
        });
    }
};
