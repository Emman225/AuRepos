<?php

use App\Domain\Partenaires\Enums\TypeDePiece;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Élargit la contrainte CHECK de `pieces_justificatives` au nouveau type
| « photo_etat_des_lieux » (P2-SEJ-02) : même table polymorphe chiffrée, un type de plus.
*/
return new class extends Migration
{
    public function up(): void
    {
        $liste = implode(',', array_map(fn ($c) => "'{$c->value}'", TypeDePiece::cases()));
        DB::statement('ALTER TABLE pieces_justificatives DROP CONSTRAINT pieces_type_connu');
        DB::statement("ALTER TABLE pieces_justificatives ADD CONSTRAINT pieces_type_connu CHECK (type IN ({$liste}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pieces_justificatives DROP CONSTRAINT pieces_type_connu');
        DB::statement("ALTER TABLE pieces_justificatives ADD CONSTRAINT pieces_type_connu CHECK (type IN ('piece_identite','titre_propriete','bail','rib','mandat','dfe','attestation_regime','rccm','bilan','autre'))");
    }
};
