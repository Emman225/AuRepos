<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Factures normalisées (FNE, DGI — CdC § 9.4) : « un séjour, une facture », proforma ou facture
 * selon le mode de règlement, avoir certifié en cas d'annulation. `lignes`, `payload_fne` et
 * `reponse_fne` sont des instantanés JSON : ce qui a été calculé, envoyé et reçu ne bouge plus
 * une fois transmis, même si les paramètres changent ensuite (même principe que `sejours.devis`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->string('type', 10);
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            // Renseignée uniquement pour un avoir : la facture qu'il annule, en tout ou partie.
            $table->foreignId('facture_origine_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->string('motif_avoir')->nullable();

            $table->unsignedBigInteger('montant_ht');
            $table->unsignedBigInteger('montant_tva');
            $table->unsignedBigInteger('autres_taxes');
            $table->unsignedBigInteger('montant_ttc');
            $table->json('lignes');

            $table->string('statut_transmission', 20)->default('a_transmettre');
            $table->string('reference_dgi')->nullable();
            $table->string('token_qr')->nullable();
            $table->string('ncc_dgi', 30)->nullable();
            $table->unsignedInteger('solde_stickers')->nullable();
            $table->text('motif_refus_dgi')->nullable();
            $table->json('payload_fne')->nullable();
            $table->json('reponse_fne')->nullable();
            $table->foreignId('transmise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transmise_le')->nullable();

            $table->foreignId('genere_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE factures ADD CONSTRAINT factures_type_connu CHECK (type IN ('proforma','facture','avoir'))");
        DB::statement("ALTER TABLE factures ADD CONSTRAINT factures_statut_connu CHECK (statut_transmission IN ('a_transmettre','transmise','refusee','non_configuree'))");
        DB::statement("ALTER TABLE factures ADD CONSTRAINT factures_avoir_motive CHECK (type <> 'avoir' OR (motif_avoir IS NOT NULL AND length(trim(motif_avoir)) > 0))");
        DB::statement("ALTER TABLE factures ADD CONSTRAINT factures_avoir_reference_une_origine CHECK (type <> 'avoir' OR facture_origine_id IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
