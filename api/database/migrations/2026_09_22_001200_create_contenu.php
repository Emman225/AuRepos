<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domaine Contenu (page d'accueil, CdC § 5.1) : bannières promotionnelles et témoignages.
 * Le blog, le carrousel et la lettre d'information (CdC § 12) restent pour l'écran
 * Paramètres › Divers (P1-BO-10), avec leur propre écran de gestion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bannieres', function (Blueprint $table): void {
            $table->id();
            $table->string('titre', 150);
            $table->string('sous_titre', 255)->nullable();
            $table->string('image_url', 500);
            $table->string('lien', 500)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('temoignages', function (Blueprint $table): void {
            $table->id();
            $table->string('nom_client', 150);
            $table->text('message');
            $table->unsignedTinyInteger('note')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->boolean('publie')->default(false);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE temoignages ADD CONSTRAINT temoignages_note_bornee CHECK (note IS NULL OR note BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('temoignages');
        Schema::dropIfExists('bannieres');
    }
};
