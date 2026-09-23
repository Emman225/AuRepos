<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Circuit propriétaire du catalogue (P3-PUB) :
|
|  - refus motivé PAR CHAMP (en plus du motif global déjà posé sur `logements.motif_refus`,
|    et du motif par photo déjà posé sur `photos_logement.motif_refus`) ;
|  - modification d'un logement PUBLIÉ : une nouvelle version attend sa validation, la version
|    publiée reste en ligne entre-temps (CdC § 7.1). Les prix (négociation, double validation)
|    et les photos ont déjà chacun leur circuit ; ceci couvre les AUTRES champs (description,
|    règles, capacité...).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table): void {
            $table->jsonb('motifs_refus_champs')->nullable()->after('motif_refus');
        });

        Schema::create('versions_logement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->cascadeOnDelete();
            $table->jsonb('valeurs');
            $table->string('statut', 20)->default('en_attente')->index();
            $table->foreignId('propose_par')->constrained('users')->restrictOnDelete();
            $table->foreignId('decide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->string('motif_refus')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE versions_logement ADD CONSTRAINT versions_logement_statut_connu CHECK (statut IN ('en_attente','validee','refusee','annulee'))");
        // Une seule version en attente à la fois par logement : pas de propositions concurrentes.
        DB::statement("CREATE UNIQUE INDEX versions_logement_une_seule_en_attente ON versions_logement (logement_id) WHERE statut = 'en_attente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('versions_logement');
        Schema::table('logements', function (Blueprint $table): void {
            $table->dropColumn('motifs_refus_champs');
        });
    }
};
