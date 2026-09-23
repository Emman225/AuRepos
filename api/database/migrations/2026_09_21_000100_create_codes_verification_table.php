<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Codes à usage unique envoyés par courriel : vérification de l'adresse à
| l'inscription et réinitialisation du mot de passe (tâche P1-API-03).
| Le code n'est jamais stocké en clair : seule son empreinte l'est.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_verification', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('usage', 40);
            $table->string('code_hash');
            $table->timestamp('expire_le');
            $table->unsignedSmallInteger('tentatives')->default(0);
            $table->timestamp('utilise_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'usage']);
        });

        // Un changement de mot de passe doit faire tomber les jetons déjà émis.
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('mot_de_passe_change_le')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('mot_de_passe_change_le'));
        Schema::dropIfExists('codes_verification');
    }
};
