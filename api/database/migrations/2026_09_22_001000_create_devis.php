<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devis (CdC § 5.1) : « un client peut faire établir un devis de séjour (prix figés par le
 * serveur) et le transformer en réservation d'un clic ; un devis en attente se supprime
 * (archivé, jamais effacé). »
 *
 * N'occupe PAS le calendrier — seul un séjour le fait. La transformation réutilise le
 * devis déjà figé ici, elle ne recalcule rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('logement_id')->constrained('logements')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->date('arrivee');
            $table->date('depart');
            $table->unsignedSmallInteger('adultes')->default(1);
            $table->unsignedSmallInteger('enfants')->default(0);
            $table->boolean('arrivee_tardive')->default(false);
            $table->boolean('depart_tardif')->default(false);
            $table->time('heure_arrivee_prevue')->nullable();
            $table->string('code_promo', 30)->nullable();
            // Le devis FIGÉ à l'établissement : montants et taux (CdC § 5.1, comme pour un séjour).
            $table->jsonb('devis');
            $table->unsignedBigInteger('net_a_payer');
            $table->unsignedBigInteger('caution');
            $table->unsignedInteger('points_utilises')->default(0);
            $table->unsignedBigInteger('reduction_points')->default(0);
            $table->string('etat', 15)->default('en_attente')->index();
            $table->foreignId('sejour_id')->nullable()->constrained('sejours')->nullOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE devis ADD CONSTRAINT devis_etat_connu CHECK (etat IN ('en_attente','transforme','archive'))");
        DB::statement('ALTER TABLE devis ADD CONSTRAINT devis_periode_coherente CHECK (depart > arrivee)');
    }

    public function down(): void
    {
        Schema::dropIfExists('devis');
    }
};
