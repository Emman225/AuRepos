<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Clôture détaillée d'une mission de ménage (P2-MEN-03) et sa rémunération (P2-MEN-04).
|
| `checklist` porte la liste des pièces contrôlées (libellé, propre : oui/non, observation) —
| une structure libre en JSON, comme `devis` sur `sejours` : ni assez stable ni assez
| interrogée en base pour mériter ses propres tables.
|
| `montant_du` est calculé à la clôture depuis le barème (App\Domain\Exploitation\Models\
| BaremeMenage) ; le paiement lui-même passe par Caisse::saisirUnDecaissement, comme tout
| reversement partenaire — `reglement_id` trace CE règlement-là, `payee_le` la date du
| paiement effectif (distincte de `terminee_le`, la date de clôture de la mission).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table): void {
            $table->jsonb('checklist')->nullable()->after('notes');
            $table->text('linge_notes')->nullable()->after('checklist');
            $table->text('produits_notes')->nullable()->after('linge_notes');
            $table->boolean('anomalie')->default(false)->after('produits_notes');
            $table->text('anomalie_description')->nullable()->after('anomalie');
            $table->unsignedInteger('montant_du')->nullable()->after('terminee_le');
            $table->foreignId('reglement_id')->nullable()->after('montant_du')->constrained('reglements')->nullOnDelete();
            $table->timestamp('payee_le')->nullable()->after('reglement_id');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reglement_id');
            $table->dropColumn(['checklist', 'linge_notes', 'produits_notes', 'anomalie', 'anomalie_description', 'montant_du', 'payee_le']);
        });
    }
};
