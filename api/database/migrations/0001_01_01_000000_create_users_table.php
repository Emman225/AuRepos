<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Comptes : agences et utilisateurs (tâche P1-API-01).
| Le schéma s'écrit table par table et reste rejouable depuis zéro — celui
| de Mon Gravier ne se reconstruisait que par import d'un dump SQL.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agences', function (Blueprint $table): void {
            $table->id();
            $table->string('nom')->unique();
            $table->string('adresse')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nom');
            $table->string('prenoms')->nullable();
            $table->string('email')->unique();
            $table->string('telephone', 30)->nullable()->unique();
            // Identifiant de connexion généré pour le personnel et envoyé par courriel (CdC § 6.8).
            $table->string('identifiant', 60)->nullable()->unique();
            $table->string('password');
            $table->string('profil', 40)->index();
            $table->string('statut', 20)->default(StatutCompte::Actif->value)->index();
            // Agence du caissier : indispensable pour encaisser (CdC § 8.1).
            $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('derniere_connexion_le')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // La base refuse elle-même un profil ou un statut inconnu.
        $profils = implode(',', array_map(fn (string $v) => "'{$v}'", Profil::valeurs()));
        $statuts = implode(',', array_map(fn (StatutCompte $s) => "'{$s->value}'", StatutCompte::cases()));
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_profil_connu CHECK (profil IN ({$profils}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_statut_connu CHECK (statut IN ({$statuts}))");

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('agences');
    }
};
