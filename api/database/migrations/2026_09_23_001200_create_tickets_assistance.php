<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Tickets d'assistance (P2-AST-01, CdC § 6.1) : soulevés par un client PENDANT un séjour
| actif (état « arrivé »), traités par l'espace assistance (Profil::AgentAssistance),
| jusqu'ici sans aucune route ni écran (audit du 22/09/2026).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets_assistance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->string('sujet');
            $table->text('message');
            $table->string('statut', 20)->default('ouvert')->index();
            $table->text('reponse')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE tickets_assistance ADD CONSTRAINT tickets_assistance_statut_connu CHECK (statut IN ('ouvert','en_cours','ferme'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets_assistance');
    }
};
