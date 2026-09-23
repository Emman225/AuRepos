<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Guichet Cautions (P2-CAU-01 à 03, CdC § 6.3 et § 8) — ferme l'écart laissé volontairement
| ouvert par le cycle de vie du séjour : « le check-in n'empêche pas encore l'arrivée si la
| caution n'a pas été explicitement encaissée » (cf. commentaire de `Guichet::prefixeDuRecu`).
|
| Dépôt et restitution restent des RÈGLEMENTS ordinaires (`reglements`, même circuit de preuve
| à quatre étapes que tout encaissement ou décaissement) : `reglements.sejour_id` les rattache
| chacun à UN séjour, sans jamais passer par une imputation — la caution n'entre pas dans le
| reste dû du séjour (`SoldeDesSejours::de`), donc jamais dans ses imputations.
|
| La retenue, elle, n'est PAS un mouvement de caisse (rien n'est décaissé sur la part retenue :
| elle reste acquise à l'entreprise) : sa propre table `retenues_caution` porte le motif
| obligatoire et la facture « frais de dégradation / retard » qu'elle fait naître.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglements', function (Blueprint $table): void {
            // Nullable : seul un règlement du guichet Cautions (dépôt ou restitution) le renseigne.
            $table->foreignId('sejour_id')->nullable()->after('tiers_id')->constrained('sejours')->nullOnDelete();
        });

        Schema::create('retenues_caution', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            // Jamais une formule de dégât automatique : toujours saisi par la main qui retient (CdC).
            $table->string('motif');
            $table->foreignId('facture_id')->constrained('factures')->restrictOnDelete();
            $table->foreignId('effectuee_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('effectuee_le');
            $table->timestamps();
        });
        DB::statement('ALTER TABLE retenues_caution ADD CONSTRAINT retenues_caution_montant_positif CHECK (montant > 0)');
        DB::statement('ALTER TABLE retenues_caution ADD CONSTRAINT retenues_caution_motif_obligatoire CHECK (length(trim(motif)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('retenues_caution');
        Schema::table('reglements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sejour_id');
        });
    }
};
