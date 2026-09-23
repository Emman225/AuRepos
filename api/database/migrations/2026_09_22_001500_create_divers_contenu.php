<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres › Divers (CdC § 12, P1-BO-10) : blog, carrousel de la page d'accueil (distinct des
 * bannières promotionnelles déjà en place, `create_contenu`) et abonnés à la lettre d'information.
 * « Modèles de messages » n'a pas de table propre : ce sont des paramètres (onglet `messages`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('titre', 200);
            $table->string('slug', 220)->unique();
            $table->string('resume', 500)->nullable();
            $table->text('contenu');
            $table->string('image_url', 500)->nullable();
            $table->string('statut', 10)->default('brouillon');
            $table->timestamp('publie_le')->nullable();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE articles ADD CONSTRAINT articles_statut_connu CHECK (statut IN ('brouillon','publie'))");

        Schema::create('carrousel', function (Blueprint $table): void {
            $table->id();
            $table->string('image_url', 500);
            $table->string('legende', 150)->nullable();
            $table->string('lien', 500)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('abonnes_newsletter', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('nom', 150)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamp('abonne_le')->useCurrent();
            $table->timestamp('desabonne_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnes_newsletter');
        Schema::dropIfExists('carrousel');
        Schema::dropIfExists('articles');
    }
};
