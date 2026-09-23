<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Domaine Maintenance (P2-MNT-01, P2-MNT-02, CdC § 6.4) : un chantier séparé de
| l'Exploitation (ménage) — même granularité que Transferts ou Repas, qui sont chacun
| leur propre domaine bien qu'« exploitation » au sens large.
|
| `tickets_maintenance.origine` distingue l'anomalie remontée en clôture de mission de
| ménage (P2-MEN-03) du signalement direct. Un ticket « bloquant » retire le logement du
| calendrier en réutilisant le SEUL mécanisme de blocage existant (App\Domain\Sejours\
| Services\Calendrier::bloquer, motif « maintenance », déjà prévu dans blocages_calendrier)
| — `blocage_calendrier_id` trace CE blocage-là, pour le lever à la résolution du ticket.
|
| Aucun profil « technicien » n'est créé : nom et contact restent du texte libre, comme
| demandé (le CdC ne fonde aucun compte pour ce rôle).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets_maintenance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->restrictOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->string('origine', 20);
            $table->string('urgence', 20);
            $table->text('description');
            $table->string('technicien_nom', 150)->nullable();
            $table->string('technicien_contact', 150)->nullable();
            $table->string('statut', 20)->default('ouvert')->index();
            $table->unsignedInteger('cout_montant')->nullable();
            $table->string('cout_impute_a', 20)->nullable();
            $table->date('indisponible_jusquau')->nullable();
            $table->foreignId('blocage_calendrier_id')->nullable()->constrained('blocages_calendrier')->nullOnDelete();
            $table->foreignId('signalee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolue_le')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE tickets_maintenance ADD CONSTRAINT tickets_maintenance_origine_connue CHECK (origine IN ('mission_menage','direct'))");
        DB::statement("ALTER TABLE tickets_maintenance ADD CONSTRAINT tickets_maintenance_urgence_connue CHECK (urgence IN ('basse','normale','haute','bloquante'))");
        DB::statement("ALTER TABLE tickets_maintenance ADD CONSTRAINT tickets_maintenance_statut_connu CHECK (statut IN ('ouvert','resolu'))");
        DB::statement("ALTER TABLE tickets_maintenance ADD CONSTRAINT tickets_maintenance_imputation_connue CHECK (cout_impute_a IS NULL OR cout_impute_a IN ('proprietaire','entreprise'))");

        // Inventaire par logement (P2-MNT-02) : nom, quantité, valeur de remplacement.
        Schema::create('articles_inventaire', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->restrictOnDelete();
            $table->string('nom', 150);
            $table->unsignedInteger('quantite')->default(1);
            $table->unsignedInteger('valeur_remplacement')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Contrats récurrents par logement (P2-MNT-02) : un simple enregistrement de rappel,
        // pas un module de gestion de contrats complet (hors périmètre, cf. rapport de tâche).
        Schema::create('contrats_recurrents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->restrictOnDelete();
            $table->string('nom', 150);
            $table->string('periodicite', 20);
            $table->date('prochain_rappel');
            $table->boolean('actif')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE contrats_recurrents ADD CONSTRAINT contrats_recurrents_periodicite_connue CHECK (periodicite IN ('mensuelle','trimestrielle','semestrielle','annuelle'))");
    }

    public function down(): void
    {
        foreach (['contrats_recurrents', 'articles_inventaire', 'tickets_maintenance'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
