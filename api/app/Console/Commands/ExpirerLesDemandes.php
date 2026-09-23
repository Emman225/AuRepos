<?php

namespace App\Console\Commands;

use App\Domain\Sejours\Services\CycleDuSejour;
use Illuminate\Console\Command;

class ExpirerLesDemandes extends Command
{
    protected $signature = 'sejours:expirer-demandes';

    protected $description = 'Annule les demandes de séjour non réglées dans le délai et libère leurs dates (CdC § 5.2)';

    public function handle(CycleDuSejour $cycle): int
    {
        $nombre = $cycle->expirerLesDemandes();
        $this->info($nombre === 0 ? 'Aucune demande à expirer.' : "{$nombre} demande(s) expirée(s) : dates libérées.");

        return self::SUCCESS;
    }
}
