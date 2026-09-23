<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Journal d'audit (tâche P1-API-07, CdC § 13.2) : qui, quand, avant / après,
| pour toute action de caisse, de tarif, de planning, de compte et de paramètre.
|
| Un journal qu'on peut corriger ne prouve rien : la base elle-même refuse
| toute modification et toute suppression de ligne, y compris à l'application.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_audit', function (Blueprint $table): void {
            $table->id();
            // Pas de clé étrangère : la trace survit au compte, même supprimé.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('auteur')->nullable();
            $table->string('profil', 40)->nullable();
            $table->string('action', 60)->index();
            $table->string('sujet_type', 80)->nullable();
            $table->unsignedBigInteger('sujet_id')->nullable();
            $table->string('sujet_libelle')->nullable();
            $table->jsonb('avant')->nullable();
            $table->jsonb('apres')->nullable();
            $table->text('recit');
            $table->string('ip', 45)->nullable();
            $table->string('adresse')->nullable();
            $table->timestamp('cree_le')->useCurrent()->index();

            $table->index(['sujet_type', 'sujet_id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_audit_inalterable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Le journal d''audit ne se modifie pas et ne s''efface pas.';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER journal_audit_inalterable
                BEFORE UPDATE OR DELETE ON journal_audit
                FOR EACH ROW EXECUTE FUNCTION journal_audit_inalterable();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_audit');
        DB::unprepared('DROP FUNCTION IF EXISTS journal_audit_inalterable()');
    }
};
