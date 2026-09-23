<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Photos des logements (tâche P1-CAT-02, CdC § 7.1).
|
| Trois fichiers par photo :
|   - l'original, sur le disque PRIVÉ (jamais servi au public, base du recadrage) ;
|   - la version d'affichage, redimensionnée et filigranée, sur le disque public ;
|   - la vignette des listes de résultats.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos_logement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->cascadeOnDelete();
            $table->string('chemin_original');
            $table->string('chemin_affichage');
            $table->string('chemin_vignette');
            // Chaque photo porte une légende : la pièce photographiée (CdC § 7.1).
            $table->string('legende', 150)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('couverture')->default(false);
            $table->unsignedInteger('largeur');
            $table->unsignedInteger('hauteur');
            $table->unsignedInteger('taille_octets');
            $table->foreignId('ajoutee_par')->nullable()->constrained('users')->nullOnDelete();
            // Une photo ajoutée par un administrateur est publiée sans autre validation, et le
            // propriétaire ne peut pas la supprimer, seulement en demander le retrait (CdC § 7.1).
            $table->boolean('ajoutee_par_administration')->default(false);
            // Validation photo par photo des dépôts du propriétaire : circuit de publication (P3-PUB-02).
            $table->string('etat', 20)->default('acceptee');
            $table->string('motif_refus')->nullable();
            $table->timestamps();

            $table->index(['logement_id', 'ordre']);
        });

        DB::statement("ALTER TABLE photos_logement ADD CONSTRAINT photos_etat_connu CHECK (etat IN ('en_attente','acceptee','refusee'))");
        // Une seule photo de couverture par logement, garantie par la base.
        DB::statement('CREATE UNIQUE INDEX photos_une_seule_couverture ON photos_logement (logement_id) WHERE couverture');
    }

    public function down(): void
    {
        Schema::dropIfExists('photos_logement');
    }
};
