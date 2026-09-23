<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Types de véhicules (transferts seulement, CdC § 6.6) : référentiel géré au back office,
| dans le même contrôleur générique que communes, quartiers, types de logement… Sert de
| base au barème (zone × type de véhicule) et au choix du client lors de sa demande.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_vehicule', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->unsignedTinyInteger('capacite')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_vehicule');
    }
};
