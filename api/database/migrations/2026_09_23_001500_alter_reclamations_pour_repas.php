<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Réclamations REPAS (P4-API-08) : extension additive de `reclamations` (P2-AST-01) plutôt
| qu'un modèle parallèle — la réclamation reste rattachée à SOIT un séjour, SOIT une commande
| de repas, jamais les deux. Choisi parce que TOUT le circuit d'avoir déjà construit
| (App\Domain\Validation\Services\DoubleValidation, ChangementsController::valider qui
| distingue `$changement->sujet instanceof Reclamation`, Caisse::saisirUnDecaissement) est
| déjà indifférent à la nature du sujet réclamé : rien n'y référence `sejour_id`
| directement, tout passe par `$reclamation->client` (déjà une colonne directe) et
| `$reclamation->sejour?->libelleAudit() ?? $reclamation->commande?->libelleAudit()` pour le
| seul texte du décaissement. Un second modèle aurait dupliqué ce circuit entier pour rien.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reclamations', function (Blueprint $table): void {
            $table->foreignId('commande_id')->nullable()->after('sejour_id')
                ->constrained('commandes_repas')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE reclamations ALTER COLUMN sejour_id DROP NOT NULL');
        DB::statement(
            'ALTER TABLE reclamations ADD CONSTRAINT reclamations_sejour_xor_commande '.
            'CHECK ((sejour_id IS NOT NULL AND commande_id IS NULL) OR (sejour_id IS NULL AND commande_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reclamations DROP CONSTRAINT reclamations_sejour_xor_commande');
        DB::statement('DELETE FROM reclamations WHERE sejour_id IS NULL');
        DB::statement('ALTER TABLE reclamations ALTER COLUMN sejour_id SET NOT NULL');

        Schema::table('reclamations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('commande_id');
        });
    }
};
