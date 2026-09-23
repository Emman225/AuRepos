<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Dette propriétaire (P3-PRO-02) et demandes de paiement (P3-PRO-04).
|
| Une charge refacturée (ménage, réparation...) vient en DÉDUCTION de ce que l'entreprise doit
| au propriétaire sur le mois où elle est imputée — jamais négative, jamais appliquée deux fois.
|
| Une demande de paiement est plafonnée au solde net (CdC § 8.7) ; son décaissement réutilise
| App\Domain\Caisse\Services\Caisse::saisirUnDecaissement, comme pour un apporteur (`reglement_id`
| n'est renseigné qu'une fois le décaissement saisi).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges_proprietaire', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained('proprietaires')->restrictOnDelete();
            $table->foreignId('logement_id')->nullable()->constrained('logements')->nullOnDelete();
            // Mois d'imputation de la charge (premier jour du mois) : celui du relevé qui la déduira.
            $table->date('periode');
            $table->string('nature', 20);
            $table->unsignedBigInteger('montant');
            $table->string('motif');
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            // Posée une fois reprise sur un relevé généré : une charge ne se déduit qu'une seule fois.
            $table->foreignId('releve_id')->nullable()->constrained('releves_proprietaire')->nullOnDelete();
            $table->timestamps();

            $table->index(['proprietaire_id', 'periode']);
        });

        DB::statement("ALTER TABLE charges_proprietaire ADD CONSTRAINT charges_proprietaire_nature_connue CHECK (nature IN ('menage','reparation','autre'))");
        DB::statement('ALTER TABLE charges_proprietaire ADD CONSTRAINT charges_proprietaire_montant_positif CHECK (montant > 0)');

        Schema::create('demandes_paiement_proprietaire', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained('proprietaires')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->string('etat', 20)->default('en_attente')->index();
            $table->foreignId('demande_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('demande_le');
            $table->foreignId('decide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->string('motif_rejet')->nullable();
            $table->foreignId('reglement_id')->nullable()->constrained('reglements')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE demandes_paiement_proprietaire ADD CONSTRAINT demandes_paiement_proprietaire_etat_connu CHECK (etat IN ('en_attente','rejetee','decaissee','annulee'))");
        DB::statement('ALTER TABLE demandes_paiement_proprietaire ADD CONSTRAINT demandes_paiement_proprietaire_montant_positif CHECK (montant > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_paiement_proprietaire');
        Schema::dropIfExists('charges_proprietaire');
    }
};
