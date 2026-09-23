<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Réclamations (P2-AST-01, CdC § 6.1) : soulevées par un client APRÈS un séjour (parti ou
| clôturé), motif obligatoire d'au moins 15 caractères (CdC, exact). Une réclamation se
| ferme sans rien, ou avec un avoir / geste commercial — la MÊME double validation que la
| réduction sur séjour (`ReductionSurSejour`) : un administrateur propose un montant motivé
| sur `avoir_montant` (le champ que `App\Domain\Validation\Services\DoubleValidation` lit et
| écrit directement), LE trésorier désigné confirme (`Parametres::tresorierDesigne()` et
| `gestionnaires.validant_2_id`), un décaissement (`Caisse::saisirUnDecaissement`) le verse.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclamations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->text('motif');
            $table->string('statut', 20)->default('ouverte')->index();
            $table->text('reponse')->nullable();
            $table->unsignedBigInteger('avoir_montant')->nullable();
            $table->string('avoir_motif')->nullable();
            $table->foreignId('reglement_id')->nullable()->constrained('reglements')->nullOnDelete();
            $table->foreignId('fermee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fermee_le')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE reclamations ADD CONSTRAINT reclamations_statut_connu CHECK (statut IN ('ouverte','en_cours','fermee'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamations');
    }
};
