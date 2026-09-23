<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications multicanal (courriel, SMS, WhatsApp) — CdC § 6.7 et § 13.2 :
 * « Tout courriel, SMS ou message WhatsApp est journalisé et relançable ;
 * un échec ne bloque jamais l'opération. »
 *
 * Le sujet et le corps sont enregistrés déjà RENDUS (placeholders remplacés) : une relance
 * renvoie exactement ce qui a été composé, même si le modèle a changé entre-temps dans les
 * Paramètres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('canal', 10);
            $table->string('modele', 30);
            $table->string('destinataire', 255);
            $table->string('sujet')->nullable();
            $table->text('corps');
            $table->json('donnees')->nullable();
            $table->string('etat', 15)->default('en_attente');
            $table->unsignedSmallInteger('tentatives')->default(0);
            $table->string('erreur')->nullable();
            $table->timestamp('envoyee_le')->nullable();
            $table->timestamps();

            $table->index(['etat', 'created_at']);
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_canal_connu CHECK (canal IN ('email','sms','whatsapp'))");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_etat_connu CHECK (etat IN ('en_attente','envoyee','echouee'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
