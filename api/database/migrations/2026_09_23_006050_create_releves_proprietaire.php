<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Relevé mensuel du propriétaire (P3-PRO-03, CdC § 7.2 et § 8.7) : nuitées consommées,
| montant brut, part/commission de l'entreprise, charges refacturées, part des cautions
| retenues, TVA du propriétaire assujetti, retenue à la source figée à sa date, net à
| reverser. Un relevé par propriétaire et par mois ; le PDF est conservé tel qu'émis et
| sert aussi d'attestation de retenue (CdC § 8.7 : « une attestation ... par bénéficiaire
| et par mois »).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releves_proprietaire', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained('proprietaires')->restrictOnDelete();
            // Premier jour du mois couvert.
            $table->date('periode');
            $table->unsignedInteger('nuitees_consommees')->default(0);
            // Brut = ce que le calcul (prix négocié ou commission) donne AVANT charges, cautions, retenue.
            $table->unsignedBigInteger('montant_brut')->default(0);
            $table->unsignedBigInteger('charges_refacturees')->default(0);
            // Part des cautions retenues qui revient au propriétaire (déjà nette de la part entreprise).
            $table->unsignedBigInteger('part_cautions')->default(0);
            $table->unsignedBigInteger('tva')->default(0);
            $table->decimal('retenue_taux', 5, 2)->default(0);
            $table->unsignedBigInteger('retenue_montant')->default(0);
            $table->string('retenue_motif')->nullable();
            // Net = brut − charges + cautions + TVA − retenue ; jamais négatif (voir service).
            $table->unsignedBigInteger('montant_net')->default(0);
            $table->string('chemin_pdf')->nullable();
            // Attestation de retenue mensuelle par bénéficiaire (CdC § 8.7) : mêmes chiffres, document à part.
            $table->string('chemin_attestation_pdf')->nullable();
            $table->timestamp('genere_le');
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();

            $table->unique(['proprietaire_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releves_proprietaire');
    }
};
