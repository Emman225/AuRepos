<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Double validation des valeurs sensibles (tâches P1-CAT-05 et P1-CAT-04).
|
| `changements_a_valider` est générique : le prix de vente d'un logement aujourd'hui,
| le pourcentage entreprise et le pourcentage plateforme sur les repas demain.
| Un administrateur propose, un AUTRE administrateur valide ; tant que ce n'est pas
| validé, l'ancienne valeur reste en vigueur.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changements_a_valider', function (Blueprint $table): void {
            $table->id();
            $table->morphs('sujet');
            $table->string('champ', 60);
            $table->jsonb('valeur_actuelle')->nullable();
            // Nullable aussi : une dérogation peut se PROPOSER À null (retour au taux global) — CdC § 7.3.
            $table->jsonb('valeur_proposee')->nullable();
            $table->string('motif')->nullable();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->foreignId('propose_par')->constrained('users')->restrictOnDelete();
            $table->foreignId('decide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->string('motif_decision')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE changements_a_valider ADD CONSTRAINT changements_statut_connu CHECK (statut IN ('en_attente','valide','refuse','annule'))");
        // La règle d'or, garantie par la base : on ne valide jamais sa propre proposition.
        DB::statement('ALTER TABLE changements_a_valider ADD CONSTRAINT changements_deux_personnes CHECK (decide_par IS NULL OR statut = \'annule\' OR decide_par <> propose_par)');
        // Un seul changement en attente par valeur : pas de propositions concurrentes.
        DB::statement("CREATE UNIQUE INDEX changements_un_seul_en_attente ON changements_a_valider (sujet_type, sujet_id, champ) WHERE statut = 'en_attente'");

        // Historique de la négociation du prix propriétaire, conservé sur la fiche (CdC § 7.2).
        Schema::create('negociations_prix', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            // Qui parle : l'administration ou le propriétaire (son espace arrive avec P3-PUB-03).
            $table->string('partie', 20);
            $table->unsignedBigInteger('montant');
            $table->string('commentaire')->nullable();
            // `proposition` : étape de la discussion ; `accord` : prix arrêté, qui devient le prix propriétaire.
            $table->string('nature', 20);
            $table->timestamps();

            $table->index(['logement_id', 'id']);
        });

        DB::statement("ALTER TABLE negociations_prix ADD CONSTRAINT negociations_partie_connue CHECK (partie IN ('administration','proprietaire'))");
        DB::statement("ALTER TABLE negociations_prix ADD CONSTRAINT negociations_nature_connue CHECK (nature IN ('proposition','accord'))");

        Schema::table('logements', function (Blueprint $table): void {
            // Qui a saisi le logement : il ne pourra pas le publier lui-même (CdC § 7.1).
            $table->foreignId('cree_par')->nullable()->after('etat_publication')->constrained('users')->nullOnDelete();
            $table->foreignId('publie_par')->nullable()->after('publie_le')->constrained('users')->nullOnDelete();
            $table->string('motif_refus')->nullable()->after('publie_par');
        });
    }

    public function down(): void
    {
        Schema::table('logements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cree_par');
            $table->dropConstrainedForeignId('publie_par');
            $table->dropColumn('motif_refus');
        });
        Schema::dropIfExists('negociations_prix');
        Schema::dropIfExists('changements_a_valider');
    }
};
