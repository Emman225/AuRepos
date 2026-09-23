<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Demandes d'annulation (P2-SEJ-06, CdC § 6.1) : un séjour CONFIRMÉ (ou déjà arrivé) ne
| s'annule plus d'un geste du client — celui-ci DEMANDE, un administrateur INSTRUIT :
| retenu / remboursable calculés par la MÊME formule que toute annulation
| (CycleDuSejour::montantRetenuSiAnnulation), remboursement par décaissement (Caisse,
| déjà prévue « pour les remboursements » dans le commentaire de la toute première
| migration de la caisse).
|
| Une seule demande EN ATTENTE à la fois par séjour : l'index partiel l'impose, comme
| « un seul propriétaire interne » ailleurs dans ce projet.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_annulation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->foreignId('demandee_par')->constrained('users')->restrictOnDelete();
            $table->text('motif_client');
            $table->string('etat', 20)->default('en_attente')->index();
            $table->unsignedBigInteger('montant_retenu')->nullable();
            $table->unsignedBigInteger('montant_rembourse')->nullable();
            $table->foreignId('reglement_id')->nullable()->constrained('reglements')->nullOnDelete();
            $table->string('motif_decision')->nullable();
            $table->foreignId('instruite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('instruite_le')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE demandes_annulation ADD CONSTRAINT demandes_annulation_etat_connu CHECK (etat IN ('en_attente','acceptee','rejetee'))");
        DB::statement('CREATE UNIQUE INDEX demandes_annulation_une_en_attente ON demandes_annulation (sejour_id) WHERE etat = \'en_attente\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_annulation');
    }
};
