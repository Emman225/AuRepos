<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liste noire (P1-BO-07, CdC § 5) : un client qui y figure ne peut plus réserver
 * (`ReservationDeSejour::controler()`), sans que l'historique de ses séjours passés ne soit
 * touché. Prévue dès la création de `clients` (2026_09_21_001100) mais différée jusqu'ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('liste_noire')->default(false)->after('a_terme_traite_le');
            $table->string('liste_noire_motif')->nullable()->after('liste_noire');
            $table->foreignId('liste_noire_par')->nullable()->after('liste_noire_motif')->constrained('users')->nullOnDelete();
            $table->timestamp('liste_noire_le')->nullable()->after('liste_noire_par');
        });

        DB::statement('ALTER TABLE clients ADD CONSTRAINT clients_liste_noire_motif_obligatoire CHECK (NOT liste_noire OR (liste_noire_motif IS NOT NULL AND length(trim(liste_noire_motif)) > 0))');
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('liste_noire_par');
            $table->dropColumn(['liste_noire', 'liste_noire_motif', 'liste_noire_le']);
        });
    }
};
