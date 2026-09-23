<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Barème des transferts (CdC § 6.6) : « Le prix vient du barème zone × type de véhicule. »
| La zone réutilise le découpage géographique existant (communes), jamais un nouveau
| référentiel. Un seul prix par couple commune × type de véhicule.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baremes_transfert', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->restrictOnDelete();
            $table->foreignId('type_vehicule_id')->constrained('types_vehicule')->restrictOnDelete();
            $table->unsignedBigInteger('prix');
            $table->timestamps();

            $table->unique(['commune_id', 'type_vehicule_id']);
        });
        DB::statement('ALTER TABLE baremes_transfert ADD CONSTRAINT baremes_transfert_prix_positif CHECK (prix >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('baremes_transfert');
    }
};
