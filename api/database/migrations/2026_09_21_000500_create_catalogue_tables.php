<?php

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Enums\ModeDeVente;
use App\Domain\Catalogue\Enums\PolitiqueAnnulation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Catalogue (tâche P1-CAT-01, CdC § 7.1) : résidence (le site) → logements (les unités).
|
| La résidence porte l'adresse, le gardiennage et les équipements communs ; le
| logement porte son type, ses pièces, sa capacité, ses règles, sa caution, sa
| durée minimale et son état de publication.
|
| Les montants sont en francs CFA, sans décimale : des entiers, jamais de flottants.
*/
return new class extends Migration
{
    public function up(): void
    {
        // Fiche propriétaire minimale ; pièces, régime fiscal et mandat arrivent avec P1-CAT-03.
        Schema::create('proprietaires', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('raison_sociale')->nullable();
            // Compte « Propriétaire interne » de l'entreprise : reversement interne, sans retenue à la source.
            $table->boolean('interne')->default(false);
            $table->timestamps();
        });

        Schema::create('residences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained('proprietaires')->restrictOnDelete();
            $table->foreignId('quartier_id')->constrained('quartiers')->restrictOnDelete();
            $table->string('nom', 150);
            $table->string('slug', 180)->unique();
            // Le public ne voit que le quartier ; l'adresse exacte est remise après confirmation (CdC § 5.1).
            $table->string('adresse')->nullable();
            $table->string('repere')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->text('consignes_acces')->nullable();
            $table->string('mode_vente', 20)->default(ModeDeVente::Logement->value);
            $table->string('disponibilite', 20)->default(Disponibilite::Disponible->value)->index();
            $table->date('reouverture_prevue_le')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipement_residence', function (Blueprint $table): void {
            $table->foreignId('residence_id')->constrained('residences')->cascadeOnDelete();
            $table->foreignId('equipement_id')->constrained('equipements')->restrictOnDelete();
            $table->primary(['residence_id', 'equipement_id']);
        });

        Schema::create('logements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('residence_id')->constrained('residences')->restrictOnDelete();
            // Un logement sans type ne peut pas être chiffré (CdC § 7.1) : le type est obligatoire.
            $table->foreignId('type_logement_id')->constrained('types_logement')->restrictOnDelete();
            $table->string('nom', 150);
            $table->string('reference', 40)->unique();
            $table->unsignedSmallInteger('nombre_pieces');
            $table->unsignedSmallInteger('nombre_chambres')->default(0);
            $table->unsignedSmallInteger('nombre_lits')->default(1);
            $table->unsignedSmallInteger('nombre_salles_de_bain')->default(1);
            $table->unsignedSmallInteger('capacite_de_base');
            $table->unsignedSmallInteger('capacite_maximale');
            $table->unsignedSmallInteger('surface_m2')->nullable();
            $table->text('description')->nullable();

            $table->boolean('fumeur_autorise')->default(false);
            $table->boolean('animaux_autorises')->default(false);
            $table->boolean('fetes_autorisees')->default(false);
            $table->text('regles_maison')->nullable();
            // Nuls = on applique les horaires par défaut des Paramètres.
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();

            $table->unsignedBigInteger('caution')->default(0);
            $table->unsignedSmallInteger('duree_minimale')->nullable();
            $table->unsignedSmallInteger('duree_maximale')->nullable();
            $table->string('politique_annulation', 20)->default(PolitiqueAnnulation::Moderee->value);

            // Prix propriétaire (négocié, jamais montré au client) et prix de vente (fixé par
            // l'administrateur, seul affiché). Leur double validation arrive avec P1-CAT-05.
            $table->unsignedBigInteger('prix_proprietaire')->nullable();
            $table->unsignedBigInteger('prix_vente')->nullable();

            $table->string('etat_publication', 20)->default(EtatPublication::Brouillon->value)->index();
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['residence_id', 'etat_publication']);
        });

        Schema::create('equipement_logement', function (Blueprint $table): void {
            $table->foreignId('logement_id')->constrained('logements')->cascadeOnDelete();
            $table->foreignId('equipement_id')->constrained('equipements')->restrictOnDelete();
            $table->primary(['logement_id', 'equipement_id']);
        });

        $liste = fn (array $cas): string => implode(',', array_map(fn ($c) => "'{$c->value}'", $cas));
        DB::statement('ALTER TABLE residences ADD CONSTRAINT residences_mode_vente_connu CHECK (mode_vente IN ('.$liste(ModeDeVente::cases()).'))');
        DB::statement('ALTER TABLE residences ADD CONSTRAINT residences_disponibilite_connue CHECK (disponibilite IN ('.$liste(Disponibilite::cases()).'))');
        DB::statement('ALTER TABLE logements ADD CONSTRAINT logements_etat_connu CHECK (etat_publication IN ('.$liste(EtatPublication::cases()).'))');
        DB::statement('ALTER TABLE logements ADD CONSTRAINT logements_politique_connue CHECK (politique_annulation IN ('.$liste(PolitiqueAnnulation::cases()).'))');
        // Règles que la base garantit elle-même, quoi que fasse l'application :
        DB::statement('ALTER TABLE logements ADD CONSTRAINT logements_capacite_coherente CHECK (capacite_de_base >= 1 AND capacite_maximale >= capacite_de_base)');
        DB::statement('ALTER TABLE logements ADD CONSTRAINT logements_durees_coherentes CHECK (duree_maximale IS NULL OR duree_minimale IS NULL OR duree_maximale >= duree_minimale)');
    }

    public function down(): void
    {
        foreach (['equipement_logement', 'logements', 'equipement_residence', 'residences', 'proprietaires'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
