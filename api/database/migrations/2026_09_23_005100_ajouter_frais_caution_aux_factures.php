<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P2-CAU-02 : une retenue de caution devient une facture normalisée « frais de dégradation /
 * retard » — un nouveau `TypeDeFacture` (`frais_caution`), distinct de `facture` pour ne jamais
 * heurter l'invariant « un séjour, une facture » (CdC § 9.4) posé sur la facture DU SÉJOUR.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE factures DROP CONSTRAINT factures_type_connu');
        DB::statement("ALTER TABLE factures ADD CONSTRAINT factures_type_connu CHECK (type IN ('proforma','facture','avoir','frais_caution'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE factures DROP CONSTRAINT factures_type_connu');
        DB::statement("ALTER TABLE factures ADD CONSTRAINT factures_type_connu CHECK (type IN ('proforma','facture','avoir'))");
    }
};
