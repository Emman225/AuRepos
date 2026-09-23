<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement d'un gestionnaire à une ou plusieurs résidences (CdC § 9.5) :
 * « un gestionnaire ne voit que ses résidences ». Table pivot simple, réutilisable
 * si d'autres profils du personnel devaient un jour être scopés de la même façon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestionnaire_residences', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('residence_id')->constrained('residences')->cascadeOnDelete();
            $table->primary(['user_id', 'residence_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestionnaire_residences');
    }
};
