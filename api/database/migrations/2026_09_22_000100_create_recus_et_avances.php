<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Reçus et avances clients (tâches P1-CAI-07 et P1-CAI-05, CdC § 4, § 8.4).
|
| Numérotation « unique pour toute la caisse » : UN compteur par année, partagé par tous les
| préfixes (RC, RC-CT, RL, RA, AV, RK, RK-R). Il est SANS TROU : une séquence PostgreSQL perd
| un numéro à chaque transaction annulée, ce qu'un contrôle fiscal n'admet pas. Le compteur est
| donc une ligne verrouillée dans la transaction qui finalise le règlement.
|
| Avance : somme déposée au guichet sans réservation. Elle « ne se rembourse pas : elle s'utilise »,
| du dépôt le plus ancien au plus récent.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compteurs', function (Blueprint $table): void {
            $table->string('cle', 40);
            $table->unsignedSmallInteger('annee');
            $table->unsignedBigInteger('valeur')->default(0);
            $table->primary(['cle', 'annee']);
        });

        Schema::table('reglements', function (Blueprint $table): void {
            // Le caissier a choisi « Enregistrer le surplus comme avance » (CdC § 8.2).
            $table->boolean('surplus_en_avance')->default(false)->after('notes');
            // Le PDF est conservé tel qu'émis : un reçu ne change plus, même si l'identité de l'entreprise change.
            $table->string('recu_chemin')->nullable()->after('numero_recu');
            // Envoyé UNE seule fois à la finalisation ; les renvois manuels ne touchent pas cette date.
            $table->timestamp('recu_envoye_le')->nullable()->after('recu_chemin');
        });

        // Une imputation d'avance ne refait pas le circuit de preuve : l'argent l'a déjà passé, au dépôt.
        DB::statement('ALTER TABLE reglements DROP CONSTRAINT reglements_etapes_dans_l_ordre');
        DB::statement("ALTER TABLE reglements ADD CONSTRAINT reglements_etapes_dans_l_ordre CHECK (
            mode = 'avance' OR (
                (etat NOT IN ('a_payer','preuve_jointe','effectue') OR valide_par IS NOT NULL)
                AND (etat NOT IN ('preuve_jointe','effectue') OR (preuve_par IS NOT NULL AND preuve_chemin IS NOT NULL))
                AND (etat <> 'effectue' OR finalise_par IS NOT NULL)
            )
        )");

        Schema::create('avances_client', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            // Le règlement EFFECTUÉ qui a apporté l'argent : dépôt d'avance, ou surplus d'un encaissement.
            $table->foreignId('reglement_id')->unique()->constrained('reglements')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->unsignedBigInteger('solde');
            $table->timestamps();

            $table->index(['client_id', 'id']);
        });
        DB::statement('ALTER TABLE avances_client ADD CONSTRAINT avances_solde_coherent CHECK (solde <= montant)');

        Schema::create('mouvements_avance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avance_id')->constrained('avances_client')->restrictOnDelete();
            // Le règlement « AV » qui a consommé cette part d'avance.
            $table->foreignId('reglement_id')->constrained('reglements')->restrictOnDelete();
            // Négatif : consommation. Positif : recrédit (séjour annulé avant confirmation).
            $table->bigInteger('montant');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_avance');
        Schema::dropIfExists('avances_client');
        Schema::table('reglements', fn (Blueprint $table) => $table->dropColumn(['surplus_en_avance', 'recu_chemin', 'recu_envoye_le']));
        Schema::dropIfExists('compteurs');
    }
};
