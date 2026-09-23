<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Confirmation d'un séjour (tâches P1-RES-09 et P1-RES-12, CdC § 6.1 et § 6.3).
|
| Codes secrets — « le code ne s'affiche jamais chez l'agent » : le client le détient, l'agent le
| SAISIT, le serveur compare. Dans Mon Gravier le code était en clair en base et n'était protégé
| que par son absence des écrans. Ici il est CHIFFRÉ (et non haché : le client doit pouvoir le
| relire dans son espace à tout moment), et aucune réponse destinée au personnel ne le contient.
| La même table servira au code de prise en charge (transfert) et au code de livraison (repas).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_secrets', function (Blueprint $table): void {
            $table->id();
            $table->morphs('sujet');
            $table->string('usage', 30);
            $table->text('code');
            $table->unsignedSmallInteger('tentatives')->default(0);
            $table->timestamp('verrouille_le')->nullable();
            $table->timestamp('utilise_le')->nullable();
            $table->foreignId('utilise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dernier_envoi_le')->nullable();
            $table->timestamps();

            $table->unique(['sujet_type', 'sujet_id', 'usage']);
        });
        DB::statement("ALTER TABLE codes_secrets ADD CONSTRAINT codes_usage_connu CHECK (usage IN ('arrivee','prise_en_charge','livraison'))");

        // Le bon de mise à disposition engage le propriétaire à livrer le logement ; l'équivalent du bon d'enlèvement (CdC § 7.2).
        Schema::create('bons_mise_a_disposition', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('sejour_id')->unique()->constrained('sejours')->restrictOnDelete();
            $table->foreignId('proprietaire_id')->constrained('proprietaires')->restrictOnDelete();
            $table->unsignedSmallInteger('nuitees');
            // Figé : ce que le propriétaire touchera par nuitée CONSOMMÉE.
            $table->unsignedBigInteger('prix_proprietaire_par_nuit')->nullable();
            $table->string('etat', 20)->default('en_attente')->index();
            // Selon le mandat, la validation est automatique ou attend l'accord du propriétaire.
            $table->boolean('valide_automatiquement')->default(false);
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE bons_mise_a_disposition ADD CONSTRAINT bons_etat_connu CHECK (etat IN ('en_attente','valide','annule'))");

        Schema::table('sejours', function (Blueprint $table): void {
            $table->foreignId('agent_accueil_id')->nullable()->after('cree_par')->constrained('users')->nullOnDelete();
            $table->foreignId('confirme_par')->nullable()->after('confirme_le')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sejours', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agent_accueil_id');
            $table->dropConstrainedForeignId('confirme_par');
        });
        Schema::dropIfExists('bons_mise_a_disposition');
        Schema::dropIfExists('codes_secrets');
    }
};
