<?php

use App\Domain\Partenaires\Enums\ModeDeRemuneration;
use App\Domain\Partenaires\Enums\NatureJuridique;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use App\Domain\Partenaires\Enums\StatutDePiece;
use App\Domain\Partenaires\Enums\TypeDePiece;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Fiche propriétaire complète et pièces justificatives (tâche P1-CAT-03, CdC § 7.2 et § 8.7).
|
| La table des pièces est polymorphe : elle servira telle quelle aux restaurateurs,
| chauffeurs, apporteurs et clients à terme. Les fichiers sont chiffrés sur le disque.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proprietaires', function (Blueprint $table): void {
            $table->string('nature', 30)->default(NatureJuridique::PersonnePhysique->value)->after('raison_sociale');
            $table->string('regime_fiscal', 30)->default(RegimeFiscal::NonRenseigne->value)->after('nature');
            $table->boolean('assujetti_tva')->default(false)->after('regime_fiscal');
            $table->string('ncc', 30)->nullable()->after('assujetti_tva');
            $table->string('rccm', 60)->nullable()->after('ncc');
            $table->string('adresse')->nullable()->after('rccm');

            // Le mandat de gestion fixe la rémunération et la validation des bons de mise à disposition.
            $table->string('mode_remuneration', 30)->default(ModeDeRemuneration::PrixNegocie->value)->after('adresse');
            $table->decimal('taux_commission', 5, 2)->nullable()->after('mode_remuneration');
            $table->decimal('part_entreprise_cautions', 5, 2)->nullable()->after('taux_commission');
            $table->date('mandat_signe_le')->nullable()->after('part_entreprise_cautions');
            $table->date('mandat_expire_le')->nullable()->after('mandat_signe_le');
            $table->boolean('bons_valides_automatiquement')->default(false)->after('mandat_expire_le');
            $table->text('notes')->nullable()->after('bons_valides_automatiquement');
        });

        $liste = fn (array $cas): string => implode(',', array_map(fn ($c) => "'{$c->value}'", $cas));
        DB::statement('ALTER TABLE proprietaires ADD CONSTRAINT proprietaires_nature_connue CHECK (nature IN ('.$liste(NatureJuridique::cases()).'))');
        DB::statement('ALTER TABLE proprietaires ADD CONSTRAINT proprietaires_regime_connu CHECK (regime_fiscal IN ('.$liste(RegimeFiscal::cases()).'))');
        DB::statement('ALTER TABLE proprietaires ADD CONSTRAINT proprietaires_mode_connu CHECK (mode_remuneration IN ('.$liste(ModeDeRemuneration::cases()).'))');
        // L'entreprise n'a qu'UN compte « Propriétaire interne ».
        DB::statement('CREATE UNIQUE INDEX proprietaires_un_seul_interne ON proprietaires (interne) WHERE interne');

        Schema::create('pieces_justificatives', function (Blueprint $table): void {
            $table->id();
            $table->morphs('titulaire');
            $table->string('type', 30);
            $table->string('chemin');
            $table->string('nom_original');
            $table->string('mime', 100);
            $table->unsignedInteger('taille_octets');
            $table->string('statut', 20)->default(StatutDePiece::EnAttente->value)->index();
            $table->string('motif_refus')->nullable();
            $table->date('expire_le')->nullable();
            $table->foreignId('deposee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verifiee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verifiee_le')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE pieces_justificatives ADD CONSTRAINT pieces_type_connu CHECK (type IN ('.$liste(TypeDePiece::cases()).'))');
        DB::statement('ALTER TABLE pieces_justificatives ADD CONSTRAINT pieces_statut_connu CHECK (statut IN ('.$liste(StatutDePiece::cases()).'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_justificatives');
        DB::statement('DROP INDEX IF EXISTS proprietaires_un_seul_interne');
        foreach (['proprietaires_nature_connue', 'proprietaires_regime_connu', 'proprietaires_mode_connu'] as $contrainte) {
            DB::statement("ALTER TABLE proprietaires DROP CONSTRAINT IF EXISTS {$contrainte}");
        }
        Schema::table('proprietaires', fn (Blueprint $table) => $table->dropColumn([
            'nature', 'regime_fiscal', 'assujetti_tva', 'ncc', 'rccm', 'adresse', 'mode_remuneration', 'taux_commission',
            'part_entreprise_cautions', 'mandat_signe_le', 'mandat_expire_le', 'bons_valides_automatiquement', 'notes',
        ]));
    }
};
