<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Transfert et accompagnement (CdC § 6.6, cas « pendant le séjour ») :
|
| « À la réservation ou pendant le séjour, le client demande un transfert : lieu de prise
|   en charge, date et heure, nombre de passagers et de bagages. Le prix vient du barème
|   zone × type de véhicule. Un chauffeur l'accueille avec une pancarte à son nom… Le client
|   remet au chauffeur son code de prise en charge. »
|
| Le code de prise en charge réutilise codes_secrets (usage 'prise_en_charge', déjà prévu
| par sa contrainte CHECK) : rien à créer ici pour le chiffrement.
|
| Rémunération du chauffeur : le CdC ne donne AUCUNE formule automatique (contrairement à
| l'apporteur) — seulement « Marge par transfert = Facturé − versé au chauffeur ». Le montant
| versé est donc saisi MANUELLEMENT par le gestionnaire au moment de l'affectation.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chauffeurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chauffeur_id')->constrained('chauffeurs')->restrictOnDelete();
            $table->foreignId('type_vehicule_id')->constrained('types_vehicule')->restrictOnDelete();
            $table->string('immatriculation', 20)->unique();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('transferts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            // Jamais nullable : cette tâche ne construit que le cas « pendant le séjour ».
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->string('lieu_de_prise_en_charge', 255);
            $table->foreignId('commune_id')->constrained('communes')->restrictOnDelete();
            $table->foreignId('type_vehicule_souhaite_id')->constrained('types_vehicule')->restrictOnDelete();
            $table->dateTime('date_heure_prevue');
            $table->unsignedSmallInteger('nombre_passagers');
            $table->unsignedSmallInteger('nombre_bagages')->default(0);
            // Calculé depuis le barème à la demande : jamais saisi par le client.
            $table->unsignedBigInteger('montant');
            $table->string('etat', 20)->default('demande')->index();
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->nullOnDelete();
            $table->foreignId('vehicule_id')->nullable()->constrained('vehicules')->nullOnDelete();
            // Négocié et saisi à la volée par le gestionnaire, jamais un pourcentage deviné.
            $table->unsignedBigInteger('montant_verse_au_chauffeur')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE transferts ADD CONSTRAINT transferts_etat_connu CHECK (etat IN ('demande','affecte','termine','annule'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('transferts');
        Schema::dropIfExists('vehicules');
        Schema::dropIfExists('chauffeurs');
    }
};
