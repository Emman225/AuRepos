<?php

namespace App\Console\Commands;

use App\Domain\PaiementEnLigne\Services\PaiementsEnLigne;
use Illuminate\Console\Command;

class ReprendreLesPaiements extends Command
{
    protected $signature = 'paiements:reprendre';

    protected $description = 'Redemande à la passerelle l’état des paiements en ligne restés en attente (CdC § 8.3)';

    public function handle(PaiementsEnLigne $paiements): int
    {
        $nombre = $paiements->reprendreLesPaiementsEnAttente();
        $this->info($nombre === 0 ? 'Aucun paiement en attente.' : "{$nombre} paiement(s) repris.");

        return self::SUCCESS;
    }
}
