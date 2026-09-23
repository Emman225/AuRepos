<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Missions de ménage (P2-MEN-01, CdC § 6.4) : avant arrivée, après départ, et ménage
| demandé (ad hoc). Un seul type porté par ce pass — `type` reste une colonne dédiée
| (comme `EtatDuSejour` et consorts) pour accueillir la maintenance plus tard (P2-MNT-01,
| hors périmètre ici) sans nouvelle migration de structure.
|
| DÉLIBÉRÉMENT ABSENT (cf. PLAN-REALISATION.md) : le déclencheur « ménage périodique long
| séjour » — le CdC le nomme sans jamais fixer d'intervalle (hebdomadaire ? bimensuel ?) ;
| l'inventer aurait été une règle métier non écrite.
|
| `origine` trace le déclencheur (avant_arrivee / apres_depart / demande) pour l'écran de
| back office et le journal d'audit, sans porter de règle métier propre.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logement_id')->constrained('logements')->restrictOnDelete();
            $table->string('type', 20)->default('menage');
            $table->foreignId('sejour_id')->nullable()->constrained('sejours')->nullOnDelete();
            $table->string('statut', 20)->default('a_faire')->index();
            $table->string('origine', 20);
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('affectee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('echeance');
            $table->text('notes')->nullable();
            $table->foreignId('demandee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('debutee_le')->nullable();
            $table->timestamp('terminee_le')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE missions ADD CONSTRAINT missions_type_connu CHECK (type IN ('menage'))");
        DB::statement("ALTER TABLE missions ADD CONSTRAINT missions_statut_connu CHECK (statut IN ('a_faire','en_cours','faite'))");
        DB::statement("ALTER TABLE missions ADD CONSTRAINT missions_origine_connue CHECK (origine IN ('avant_arrivee','apres_depart','demande'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
