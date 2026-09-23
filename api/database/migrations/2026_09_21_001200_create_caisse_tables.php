<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Caisse (tâches P1-CAI-01 à 03, CdC § 4 et § 8) — règle reprise de Mon Gravier.
|
| « Aucun règlement encaissé au guichet n'est acquis d'un seul geste. »
|
|   1. SAISIE        un administrateur ou un caissier          → en attente de validation
|   2. VALIDATION    un second administrateur, autre           → à payer
|   3. PREUVE        un troisième administrateur, autre encore → à payer, preuve jointe
|   4. FINALISATION  ce même troisième administrateur          → effectué
|
| Tant que le circuit n'est pas terminé, la somme ne compte pas comme payée. Le même circuit
| sert aux décaissements : reversements, restitutions de caution, remboursements.
| Les règles de personnes sont garanties par la BASE, pas seulement par l'application.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglements', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('sens', 20);
            $table->string('guichet', 30);
            // L'agence n'est jamais choisie : c'est celle du caissier connecté (CdC § 8.1).
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            // Le client qui paie, ou le partenaire que l'on paie.
            $table->foreignId('tiers_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->string('mode', 20);
            // N° de transaction mobile money, de chèque, de virement…
            $table->string('reference_du_mode', 100)->nullable();
            $table->text('notes');
            $table->string('etat', 20)->default('en_attente')->index();

            $table->foreignId('saisi_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('saisi_le');
            $table->foreignId('valide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('preuve_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('preuve_le')->nullable();
            $table->string('preuve_chemin')->nullable();
            $table->string('preuve_nom')->nullable();
            $table->string('preuve_mime', 100)->nullable();
            $table->foreignId('finalise_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalise_le')->nullable();
            $table->foreignId('rejete_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejete_le')->nullable();
            $table->string('motif_rejet')->nullable();

            // Attribué à la finalisation seulement : avant, le reçu n'existe pas (CdC § 4).
            $table->string('numero_recu', 40)->nullable()->unique();
            $table->timestamps();

            $table->index(['tiers_id', 'etat']);
        });

        DB::statement("ALTER TABLE reglements ADD CONSTRAINT reglements_sens_connu CHECK (sens IN ('encaissement','decaissement'))");
        DB::statement("ALTER TABLE reglements ADD CONSTRAINT reglements_etat_connu CHECK (etat IN ('en_attente','a_payer','preuve_jointe','effectue','rejete'))");
        DB::statement("ALTER TABLE reglements ADD CONSTRAINT reglements_mode_connu CHECK (mode IN ('especes','mobile_money','carte','virement','cheque','avance','canal_externe'))");
        DB::statement('ALTER TABLE reglements ADD CONSTRAINT reglements_montant_positif CHECK (montant > 0)');
        DB::statement('ALTER TABLE reglements ADD CONSTRAINT reglements_notes_obligatoires CHECK (length(trim(notes)) > 0)');

        // Les trois personnes du circuit, garanties par la base :
        DB::statement('ALTER TABLE reglements ADD CONSTRAINT reglements_validation_par_un_autre CHECK (valide_par IS NULL OR valide_par <> saisi_par)');
        DB::statement('ALTER TABLE reglements ADD CONSTRAINT reglements_preuve_par_un_troisieme CHECK (preuve_par IS NULL OR (preuve_par <> saisi_par AND preuve_par <> valide_par))');
        DB::statement('ALTER TABLE reglements ADD CONSTRAINT reglements_finalisation_par_le_meme CHECK (finalise_par IS NULL OR finalise_par = preuve_par)');
        // Et on ne saute pas d'étape :
        DB::statement("ALTER TABLE reglements ADD CONSTRAINT reglements_etapes_dans_l_ordre CHECK (
            (etat NOT IN ('a_payer','preuve_jointe','effectue') OR valide_par IS NOT NULL)
            AND (etat NOT IN ('preuve_jointe','effectue') OR (preuve_par IS NOT NULL AND preuve_chemin IS NOT NULL))
            AND (etat <> 'effectue' OR finalise_par IS NOT NULL)
        )");

        // Un règlement peut solder plusieurs affaires d'un même client, de la plus ancienne à la plus récente.
        Schema::create('imputations_reglement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reglement_id')->constrained('reglements')->cascadeOnDelete();
            $table->morphs('affaire');
            $table->unsignedBigInteger('montant');
            $table->timestamps();
        });
        DB::statement('ALTER TABLE imputations_reglement ADD CONSTRAINT imputations_montant_positif CHECK (montant > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('imputations_reglement');
        Schema::dropIfExists('reglements');
    }
};
