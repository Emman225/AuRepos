<?php

use App\Domain\Sejours\Enums\StatutAvis;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avis vérifiés de fin de séjour (CdC § 5.1, P2-AVI-01) : un avis par séjour, jamais avant
 * sa clôture, modéré par un administrateur avant toute publication publique. Ajoute aussi
 * « mise en avant » sur les résidences (page d'accueil), même principe que sur les logements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avis', function (Blueprint $table): void {
            $table->id();
            // Un séjour ne peut recevoir qu'un seul avis : la borne, c'est LUI, pas le client
            // (qui peut avoir plusieurs séjours, donc plusieurs avis, un par séjour terminé).
            $table->foreignId('sejour_id')->unique()->constrained('sejours')->cascadeOnDelete();
            $table->unsignedTinyInteger('note');
            $table->string('commentaire', 1000)->nullable();
            $table->string('statut', 15)->default(StatutAvis::EnAttente->value)->index();
            $table->foreignId('modere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('modere_le')->nullable();
            $table->string('motif_refus', 255)->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE avis ADD CONSTRAINT avis_note_bornee CHECK (note BETWEEN 1 AND 5)');
        $liste = implode(',', array_map(fn ($c) => "'{$c->value}'", StatutAvis::cases()));
        DB::statement("ALTER TABLE avis ADD CONSTRAINT avis_statut_connu CHECK (statut IN ({$liste}))");

        Schema::table('residences', function (Blueprint $table): void {
            $table->boolean('mise_en_avant')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('residences', function (Blueprint $table): void {
            $table->dropColumn('mise_en_avant');
        });
        Schema::dropIfExists('avis');
    }
};
