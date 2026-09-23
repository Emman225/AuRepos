<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Séjours et calendrier (tâches P1-RES-01 à 03, CdC § 4 « Disponibilité »).
|
| « Un logement est une unité indivisible : deux séjours ne peuvent pas se chevaucher. »
|
| La règle est portée par la BASE, pas seulement par l'application : la table
| `occupations` reçoit TOUT ce qui occupe un logement (séjour, blocage de maintenance,
| usage du propriétaire, et demain les réservations des canaux externes), et une
| contrainte d'exclusion y interdit tout chevauchement. Deux clients qui valident la
| même nuit au même instant : l'un des deux est refusé par PostgreSQL, quoi qu'il arrive.
|
| Un séjour occupe [arrivée, départ[ : le jour du départ est libre pour l'arrivée suivante.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sejours', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('logement_id')->constrained('logements')->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('arrivee');
            $table->date('depart');
            $table->unsignedSmallInteger('adultes')->default(1);
            $table->unsignedSmallInteger('enfants')->default(0);
            $table->string('etat', 20)->default('demande')->index();
            // direct (site, application) · reception (téléphone, walk-in) · booking · airbnb · expedia
            $table->string('canal', 20)->default('direct');
            // Le devis FIGÉ à la réservation : montants et taux. Un changement de paramètre ne le touche plus (CdC § 5.4).
            $table->jsonb('devis')->nullable();
            $table->unsignedBigInteger('net_a_payer')->default(0);
            $table->unsignedBigInteger('caution')->default(0);
            // Une demande non réglée expire et libère ses dates (CdC § 5.2).
            $table->timestamp('expire_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['logement_id', 'arrivee']);
        });

        DB::statement("ALTER TABLE sejours ADD CONSTRAINT sejours_etat_connu CHECK (etat IN ('demande','confirme','arrive','parti','cloture','annule','no_show'))");
        DB::statement('ALTER TABLE sejours ADD CONSTRAINT sejours_periode_coherente CHECK (depart > arrivee)');
        DB::statement('ALTER TABLE sejours ADD CONSTRAINT sejours_occupants_coherents CHECK (adultes >= 1)');

        Schema::create('blocages_calendrier', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->cascadeOnDelete();
            // « Du … au … » : les deux jours sont bloqués.
            $table->date('debut');
            $table->date('fin');
            $table->string('motif', 30);
            $table->string('commentaire')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE blocages_calendrier ADD CONSTRAINT blocages_motif_connu CHECK (motif IN ('maintenance','usage_proprietaire','saison_fermee','canal_externe'))");
        DB::statement('ALTER TABLE blocages_calendrier ADD CONSTRAINT blocages_periode_coherente CHECK (fin >= debut)');

        // `btree_gist` donne à GiST les opérateurs des types courants (ici le `=` sur un entier),
        // sans quoi la contrainte d'exclusion ci-dessous est refusée : « data type bigint has no
        // default operator class for access method gist ». À créer AVANT, sinon une base neuve
        // ne peut pas migrer du tout — les bases existantes l'avaient reçue à la main.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('occupations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->cascadeOnDelete();
            $table->foreignId('sejour_id')->nullable()->unique()->constrained('sejours')->cascadeOnDelete();
            $table->foreignId('blocage_id')->nullable()->unique()->constrained('blocages_calendrier')->cascadeOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE occupations ADD COLUMN periode daterange NOT NULL');
        DB::statement('ALTER TABLE occupations ADD CONSTRAINT occupations_une_seule_source CHECK ((sejour_id IS NULL) <> (blocage_id IS NULL))');
        // LA règle : jamais deux occupations du même logement sur une même nuit.
        DB::statement('ALTER TABLE occupations ADD CONSTRAINT occupations_sans_chevauchement EXCLUDE USING gist (logement_id WITH =, periode WITH &&)');

        // Historique du bouton « Occupée / Disponible » : il compte dans le taux de disponibilité du propriétaire (CdC § 6.2).
        Schema::create('fermetures_residence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('residence_id')->constrained('residences')->cascadeOnDelete();
            $table->timestamp('fermee_le');
            $table->timestamp('rouverte_le')->nullable();
            $table->date('reouverture_prevue_le')->nullable();
            $table->string('motif')->nullable();
            $table->foreignId('fermee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rouverte_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fermetures_residence', 'occupations', 'blocages_calendrier', 'sejours'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
