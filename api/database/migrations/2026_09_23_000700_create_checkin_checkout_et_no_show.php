<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Check-in, check-out et no-show (P2-SEJ-01, 04, 05, CdC § 4 et § 6.3).
|
| Le code d'arrivée existe déjà (`codes_secrets`, usage « arrivee », généré à la
| confirmation) : il ne manquait que sa VÉRIFICATION au check-in. `agent_accueil_id`
| existe déjà lui aussi (assigné à la confirmation) ; le check-in le pose s'il ne
| l'était pas encore, et il devient alors « l'agent qui a réellement accueilli ».
|
| Le no-show réutilise `montant_retenu_annulation` et `motif_annulation` : c'est le
| même concept (« ce qui a été retenu, et pourquoi, sur un séjour qui ne s'est pas
| honoré ») que l'annulation, avec la MÊME formule (CycleDuSejour::montantRetenuSiAnnulation).
| Seule sa propre date, `no_show_le`, lui est dédiée.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sejours', function (Blueprint $table): void {
            $table->timestamp('arrive_le')->nullable()->after('confirme_par');
            $table->timestamp('no_show_le')->nullable()->after('annule_le');

            // Check-out (P2-SEJ-04) : la caution retenue est TOUJOURS une saisie manuelle et
            // motivée — jamais une formule de dégât automatique (hors périmètre, cf. CdC).
            $table->timestamp('parti_le')->nullable()->after('arrive_le');
            $table->foreignId('checkout_par')->nullable()->after('parti_le')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('caution_retenue')->default(0)->after('checkout_par');
            $table->string('caution_retenue_motif')->nullable()->after('caution_retenue');
        });
    }

    public function down(): void
    {
        Schema::table('sejours', function (Blueprint $table): void {
            $table->dropColumn(['arrive_le', 'no_show_le', 'parti_le', 'caution_retenue', 'caution_retenue_motif']);
            $table->dropConstrainedForeignId('checkout_par');
        });
    }
};
