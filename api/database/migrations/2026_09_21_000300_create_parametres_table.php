<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Valeurs des paramètres (tâche P1-PAR-01). Les définitions sont dans
| config/parametres.php ; seules les valeurs modifiées sont stockées ici.
| L'historique des changements est tenu par le journal d'audit.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres', function (Blueprint $table): void {
            $table->id();
            $table->string('cle', 100)->unique();
            $table->jsonb('valeur')->nullable();
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};
