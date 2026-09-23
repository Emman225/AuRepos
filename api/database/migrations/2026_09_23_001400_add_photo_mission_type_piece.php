<?php

use App\Domain\Partenaires\Enums\TypeDePiece;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Élargit la contrainte CHECK de `pieces_justificatives` au nouveau type
| « photo_mission » (P2-MEN-03) : même table polymorphe chiffrée, un type de plus —
| même geste que 2026_09_23_001000_add_photo_etat_des_lieux_type_piece.php.
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
        DB::statement("ALTER TABLE pieces_justificatives ADD CONSTRAINT pieces_type_connu CHECK (type IN ('piece_identite','titre_propriete','bail','rib','mandat','dfe','attestation_regime','rccm','bilan','photo_etat_des_lieux','autre'))");
    }
};
