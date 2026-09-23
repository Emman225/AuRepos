<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Réservation (tâches P1-RES-05 à 07, CdC § 4 et § 5.2).
|
| « Tous les éléments sont figés à la réservation : un changement de paramètre ne touche
| pas un séjour déjà enregistré. » Le séjour garde donc sa propre copie du devis, du prix
| propriétaire, de l'acompte exigé et de la politique d'annulation.
*/
return new class extends Migration
{
    public function up(): void
    {
        // Fiche client minimale ; crédit à terme, liste noire et pièces arrivent avec P1-RES-08 et P1-BO-07.
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // Gabarit de facture normalisée : particulier, entreprise, administration, étranger (CdC § 9.4).
            $table->string('nature', 10)->default('b2c');
            $table->string('raison_sociale')->nullable();
            $table->string('ncc', 30)->nullable();
            // Bascules de TVA, séparées pour l'hébergement et le transfert, avec leur motif DGI.
            $table->boolean('tva_hebergement')->default(true);
            $table->boolean('tva_transfert')->default(true);
            $table->string('code_exoneration', 10)->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_nature_connue CHECK (nature IN ('b2c','b2b','b2g','b2f'))");
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_exoneration_connue CHECK (code_exoneration IS NULL OR code_exoneration IN ('TVAD','TVAC'))");

        Schema::table('sejours', function (Blueprint $table): void {
            $table->string('mode_reglement', 20)->default('agence')->after('canal');
            $table->time('heure_arrivee_prevue')->nullable()->after('enfants');
            // Obligatoire pour un client entreprise, administration ou étranger (CdC § 5.2).
            $table->string('bon_de_commande', 100)->nullable()->after('heure_arrivee_prevue');
            $table->string('bon_de_commande_fichier')->nullable()->after('bon_de_commande');

            // Valeurs FIGÉES à la réservation.
            $table->unsignedBigInteger('prix_proprietaire_par_nuit')->nullable()->after('caution');
            $table->unsignedBigInteger('acompte_exige')->default(0)->after('prix_proprietaire_par_nuit');
            $table->string('politique_annulation', 20)->default('moderee')->after('acompte_exige');
            $table->decimal('annulation_pourcentage_retenu', 5, 2)->default(0)->after('politique_annulation');
            $table->unsignedSmallInteger('annulation_delai_jours')->default(0)->after('annulation_pourcentage_retenu');

            $table->timestamp('confirme_le')->nullable()->after('expire_le');
            $table->timestamp('annule_le')->nullable()->after('confirme_le');
            $table->string('motif_annulation')->nullable()->after('annule_le');
            $table->unsignedBigInteger('montant_retenu_annulation')->default(0)->after('motif_annulation');
        });
        DB::statement("ALTER TABLE sejours ADD CONSTRAINT sejours_reglement_connu CHECK (mode_reglement IN ('en_ligne','agence','a_terme','canal_externe'))");

        // Fiche de police : identité des occupants, relevée dès la réservation (CdC § 5.2 et § 6.3).
        Schema::create('occupants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sejour_id')->constrained('sejours')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('prenoms', 150)->nullable();
            $table->boolean('enfant')->default(false);
            $table->string('type_piece', 30)->nullable();
            // Chiffré par l'application : donnée personnelle sensible (ARTCI).
            $table->text('numero_piece')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occupants');
        Schema::table('sejours', fn (Blueprint $table) => $table->dropColumn([
            'mode_reglement', 'heure_arrivee_prevue', 'bon_de_commande', 'bon_de_commande_fichier', 'prix_proprietaire_par_nuit',
            'acompte_exige', 'politique_annulation', 'annulation_pourcentage_retenu', 'annulation_delai_jours',
            'confirme_le', 'annule_le', 'motif_annulation', 'montant_retenu_annulation',
        ]));
        Schema::dropIfExists('clients');
    }
};
