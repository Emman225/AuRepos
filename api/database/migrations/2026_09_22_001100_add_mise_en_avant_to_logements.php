<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Résidences et logements mis en avant sur la page d'accueil (CdC § 5.1). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table): void {
            $table->boolean('mise_en_avant')->default(false)->after('etat_publication');
        });
    }

    public function down(): void
    {
        Schema::table('logements', function (Blueprint $table): void {
            $table->dropColumn('mise_en_avant');
        });
    }
};
