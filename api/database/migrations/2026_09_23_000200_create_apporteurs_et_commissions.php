<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Apporteurs d'affaires et leurs commissions (tâche P3-APP-01, CdC — apporteurs) :
|
| « Parrainé par un code ; commission calculée sur chaque tranche encaissée d'un séjour,
|   jamais sur le total ni sur la caution. »
|
| Une ligne de commission par RÈGLEMENT encaissé (pas par séjour) : un séjour payé en
| plusieurs tranches donne plusieurs commissions. L'unicité sur reglement_id est le
| garde-fou anti-doublon si un événement de fin de circuit est rejoué.
|
| Le reversement à l'apporteur n'a pas de mécanisme propre : il réutilise le décaissement
| générique de App\Domain\Caisse\Services\Caisse (guichet « dettes partenaires »), déjà
| utilisé pour reverser un propriétaire.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apporteurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->decimal('pourcentage', 5, 2)->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE apporteurs ADD CONSTRAINT apporteurs_pourcentage_valide CHECK (pourcentage >= 0 AND pourcentage <= 100)');

        Schema::table('users', function (Blueprint $table): void {
            // Le CLIENT qui a été parrainé par cet apporteur (les autres profils ne s'en servent jamais).
            $table->foreignId('parraine_par_id')->nullable()->after('agence_id')->constrained('apporteurs')->nullOnDelete();
        });

        Schema::create('commissions_apporteurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('apporteur_id')->constrained('apporteurs')->restrictOnDelete();
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            // Une seule commission par règlement encaissé : le garde-fou anti-doublon (idempotence).
            $table->foreignId('reglement_id')->unique()->constrained('reglements')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->timestamps();
        });
        DB::statement('ALTER TABLE commissions_apporteurs ADD CONSTRAINT commissions_apporteurs_montant_positif CHECK (montant >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions_apporteurs');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('parraine_par_id'));
        Schema::dropIfExists('apporteurs');
    }
};
