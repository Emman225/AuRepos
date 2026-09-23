<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Extras (P2-EXT-01, CdC — « extras, transferts, assistance ») : catalogue admin-modifiable
| (le CdC n'énumère aucune liste fixe — « late check-out », « lit bébé »… sont des exemples,
| pas une nomenclature fermée), commande PENDANT un séjour déjà arrivé (même règle que les
| tickets d'assistance : App\Domain\Assistance\Services\TicketsAssistance), affectation
| optionnelle à un membre du personnel qui l'exécute, service marqué fait.
|
| Facturation : JAMAIS une ligne du net à payer figé du séjour, comme les repas et les
| transferts (App\Domain\Sejours\Services\CheckOut::consommations) — son propre circuit
| d'encaissement, au guichet Extras (P2-TRF-03), via imputations_reglement déjà polymorphe
| (son commentaire prévoyait déjà « extras, transferts, repas demain »).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extras', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('prix');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('commandes_extras', function (Blueprint $table): void {
            $table->id();
            // 40, pas 20 : le temps de recevoir son id, la référence tient temporairement
            // « TMP-<uuid> » (même patron que App\Domain\Repas\Models\Commande).
            $table->string('reference', 40)->unique();
            // Jamais nullable : « pendant le séjour », jamais à la réservation.
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->foreignId('extra_id')->constrained('extras')->restrictOnDelete();
            $table->unsignedSmallInteger('quantite')->default(1);
            // Figés à la commande : un extra renommé ou re-tarifé ensuite ne change jamais une commande déjà passée.
            $table->string('nom_extra', 150);
            $table->unsignedBigInteger('prix_unitaire');
            $table->unsignedBigInteger('montant_total');
            $table->string('etat', 20)->default('demande')->index();
            // Qui l'exécute : un membre du personnel, si pertinent (pas toutes les commandes en ont besoin).
            $table->foreignId('affecte_a_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('affecte_le')->nullable();
            $table->timestamp('fournie_le')->nullable();
            // Qui a saisi la commande au nom du client, quand ce n'est pas le client lui-même (guichet, réception).
            $table->foreignId('demande_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE commandes_extras ADD CONSTRAINT commandes_extras_etat_connu CHECK (etat IN ('demande','confirmee','fournie','annulee','refusee'))");
        DB::statement('ALTER TABLE commandes_extras ADD CONSTRAINT commandes_extras_quantite_positive CHECK (quantite > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes_extras');
        Schema::dropIfExists('extras');
    }
};
