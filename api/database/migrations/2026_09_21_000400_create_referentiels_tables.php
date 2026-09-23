<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Référentiels (tâche P1-PAR-05) : découpage géographique en cascade
| région › ville › commune › quartier (base de la recherche publique et des
| zones de transfert), types de logement, équipements, statuts métier.
|
| Un élément déjà utilisé ne se supprime pas : il se désactive (`actif`),
| et disparaît alors des listes de choix sans casser l'historique.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('villes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->string('nom', 100);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['region_id', 'nom']);
        });

        Schema::create('communes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ville_id')->constrained('villes')->restrictOnDelete();
            $table->string('nom', 100);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['ville_id', 'nom']);
        });

        Schema::create('quartiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->restrictOnDelete();
            $table->string('nom', 100);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['commune_id', 'nom']);
        });

        // Le type de logement joue le rôle de l'unité de mesure de Mon Gravier :
        // la grille tarifaire et la rémunération des agents s'y rattachent (CdC § 7.1).
        Schema::create('types_logement', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom', 100)->unique();
            $table->unsignedSmallInteger('nombre_pieces')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('equipements', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100)->unique();
            // `residence` : commun au site (piscine, parking…) ; `logement` : propre à l'unité.
            $table->string('portee', 20)->default('logement');
            $table->string('icone', 60)->nullable();
            // Proposé comme filtre dans le moteur de recherche public (CdC § 5.1).
            $table->boolean('filtre_recherche')->default(false);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Libellés et couleurs d'affichage des états ; les transitions, elles, restent dans le code.
        Schema::create('statuts_metier', function (Blueprint $table): void {
            $table->id();
            $table->string('domaine', 40);
            $table->string('code', 40);
            $table->string('libelle', 100);
            $table->string('couleur', 9)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['domaine', 'code']);
        });
    }

    public function down(): void
    {
        foreach (['statuts_metier', 'equipements', 'types_logement', 'quartiers', 'communes', 'villes', 'regions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
