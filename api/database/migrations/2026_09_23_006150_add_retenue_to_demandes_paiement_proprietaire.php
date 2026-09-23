<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Le montant demandé est déjà NET (il est plafonné au solde net, CdC § 8.7) ; ces champs
| gardent la retenue qui a produit ce net, figée à la date de la DEMANDE, pour que le
| bordereau envoyé au propriétaire affiche brut, retenue et net comme l'exige le CdC.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes_paiement_proprietaire', function (Blueprint $table): void {
            $table->unsignedBigInteger('montant_brut_equivalent')->nullable()->after('montant');
            $table->decimal('retenue_taux', 5, 2)->nullable()->after('montant_brut_equivalent');
            $table->unsignedBigInteger('retenue_montant')->nullable()->after('retenue_taux');
            $table->string('retenue_motif')->nullable()->after('retenue_montant');
        });
    }

    public function down(): void
    {
        Schema::table('demandes_paiement_proprietaire', function (Blueprint $table): void {
            $table->dropColumn(['montant_brut_equivalent', 'retenue_taux', 'retenue_montant', 'retenue_motif']);
        });
    }
};
