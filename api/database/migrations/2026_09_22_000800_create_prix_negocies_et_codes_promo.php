<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prix négociés par client et codes promo (CdC § 7.3) :
 * « Prix négociés par client (entreprises à terme) et par type de logement : ils priment
 * sur tout le reste. Codes promo, offres long séjour, tarif de dernière minute (activable
 * par résidence). »
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prix_negocies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('type_logement_id')->constrained('types_logement')->cascadeOnDelete();
            $table->unsignedBigInteger('tarif_par_nuit');
            $table->boolean('actif')->default(true);
            $table->string('notes')->nullable();
            // Qui a arrêté le tarif EN VIGUEUR — une nouvelle négociation remplace la précédente,
            // l'historique complet reste dans le journal d'audit.
            $table->foreignId('modifie_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Une seule négociation par client et par type : la dernière remplace la précédente.
            $table->unique(['client_id', 'type_logement_id']);
        });

        Schema::create('codes_promo', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 15);
            $table->unsignedBigInteger('valeur');
            $table->date('date_debut');
            $table->date('date_fin');
            // null = valable sur toutes les résidences ; sinon activable par résidence (CdC § 7.3).
            $table->foreignId('residence_id')->nullable()->constrained('residences')->cascadeOnDelete();
            $table->boolean('actif')->default(true);
            $table->string('description')->nullable();
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE codes_promo ADD CONSTRAINT codes_promo_type_connu CHECK (type IN ('pourcentage','montant'))");
        DB::statement('ALTER TABLE codes_promo ADD CONSTRAINT codes_promo_periode_valide CHECK (date_fin >= date_debut)');
        DB::statement('ALTER TABLE codes_promo ADD CONSTRAINT codes_promo_pourcentage_borne CHECK (type <> \'pourcentage\' OR valeur <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_promo');
        Schema::dropIfExists('prix_negocies');
    }
};
