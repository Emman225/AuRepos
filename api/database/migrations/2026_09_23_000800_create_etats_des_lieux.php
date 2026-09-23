<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| État des lieux d'entrée et de sortie (P2-SEJ-02, CdC § 6.3).
|
| Deux états par séjour au plus (un « entree », un « sortie »), chacun avec ses lignes
| d'inventaire. La comparaison sortie / entrée se fait par LIBELLÉ identique — jamais
| une clé étrangère fragile entre deux lignes saisies à des moments différents, par
| des agents différents. Les photos par ligne réutilisent le mécanisme chiffré des
| pièces justificatives (polymorphe) : un nouveau type y est ajouté, pas un second
| mécanisme de stockage.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etats_des_lieux', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sejour_id')->constrained('sejours')->cascadeOnDelete();
            $table->string('type', 10);
            $table->text('commentaire_general')->nullable();
            // Signature à l'écran : chiffrée comme une pièce sensible, jamais en clair sur le disque.
            $table->text('signature')->nullable();
            $table->timestamp('signe_le')->nullable();
            $table->foreignId('etabli_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sejour_id', 'type']);
        });
        DB::statement("ALTER TABLE etats_des_lieux ADD CONSTRAINT etats_des_lieux_type_connu CHECK (type IN ('entree','sortie'))");

        Schema::create('lignes_etat_des_lieux', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('etat_des_lieux_id')->constrained('etats_des_lieux')->cascadeOnDelete();
            $table->string('libelle', 150);
            $table->text('observation')->nullable();
            $table->unsignedSmallInteger('ordre')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_etat_des_lieux');
        Schema::dropIfExists('etats_des_lieux');
    }
};
