<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Paiement en ligne (tâche P1-CAI-08, CdC § 5.2 et § 8.3) — porté de Mon Gravier.
|
| Le circuit de preuve à trois personnes n'a pas de sens ici : c'est la PASSERELLE qui prouve,
| et le serveur la réinterroge avant de croire quoi que ce soit. Un paiement confirmé crée donc
| un règlement déjà « effectué », au guichet « en ligne », numéroté RL-AAAA-NNN (CdC § 8.4).
|
| Trois filets, repris de Mon Gravier :
|   1. le RAPPEL de la passerelle (webhook), traité une seule fois ;
|   2. la VÉRIFICATION serveur à serveur, que le client peut déclencher au retour ;
|   3. la REPRISE planifiée des paiements restés en attente.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_en_ligne', function (Blueprint $table): void {
            // Référence que nous transmettons à la passerelle ; elle nous la rend à chaque échange.
            $table->string('reference', 60)->primary();
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->string('etat', 20)->default('initie')->index();
            $table->string('passerelle', 30);
            // Identifiant de la transaction chez la passerelle, connu après son acceptation.
            $table->string('reference_passerelle', 100)->nullable();
            $table->string('mode_constate', 20)->nullable();
            $table->string('url_paiement', 2048)->nullable();
            // Le règlement créé quand le paiement est confirmé : preuve qu'on ne l'a créé qu'une fois.
            $table->foreignId('reglement_id')->nullable()->unique()->constrained('reglements')->restrictOnDelete();
            // Réponses brutes de la passerelle, pour le jour où un client conteste.
            $table->jsonb('dernier_echange')->nullable();
            $table->unsignedSmallInteger('verifications')->default(0);
            $table->timestamp('verifie_le')->nullable();
            $table->string('motif_echec')->nullable();
            $table->timestamps();

            $table->index(['etat', 'created_at']);
        });

        DB::statement("ALTER TABLE paiements_en_ligne ADD CONSTRAINT paiements_etat_connu CHECK (etat IN ('initie','en_attente','reussi','echoue','expire'))");
        DB::statement('ALTER TABLE paiements_en_ligne ADD CONSTRAINT paiements_montant_positif CHECK (montant > 0)');
        // Un paiement réussi a forcément produit son règlement, et un seul.
        DB::statement("ALTER TABLE paiements_en_ligne ADD CONSTRAINT paiements_reussi_a_son_reglement CHECK (etat <> 'reussi' OR reglement_id IS NOT NULL)");

        // Le guichet « en ligne » se passe des trois personnes : la passerelle et sa re-vérification font preuve.
        DB::statement('ALTER TABLE reglements DROP CONSTRAINT reglements_etapes_dans_l_ordre');
        DB::statement("ALTER TABLE reglements ADD CONSTRAINT reglements_etapes_dans_l_ordre CHECK (
            mode = 'avance' OR guichet = 'en_ligne' OR (
                (etat NOT IN ('a_payer','preuve_jointe','effectue') OR valide_par IS NOT NULL)
                AND (etat NOT IN ('preuve_jointe','effectue') OR (preuve_par IS NOT NULL AND preuve_chemin IS NOT NULL))
                AND (etat <> 'effectue' OR finalise_par IS NOT NULL)
            )
        )");
        // Un règlement en ligne est toujours rattaché à son paiement : aucun ne peut être créé « à la main ».
        DB::statement("ALTER TABLE reglements ADD CONSTRAINT reglements_en_ligne_sans_agence CHECK (guichet <> 'en_ligne' OR saisi_par = tiers_id)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reglements DROP CONSTRAINT IF EXISTS reglements_en_ligne_sans_agence');
        Schema::dropIfExists('paiements_en_ligne');
    }
};
