<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Services\Notificateur;
use Illuminate\Console\Command;

class RelancerLesNotifications extends Command
{
    protected $signature = 'notifications:reprendre';

    protected $description = 'Relance les notifications restées en attente ou échouées (CdC § 13.2)';

    public function handle(Notificateur $notificateur): int
    {
        $nombre = $notificateur->reprendre();
        $this->info($nombre === 0 ? 'Aucune notification à relancer.' : "{$nombre} notification(s) reprise(s).");

        return self::SUCCESS;
    }
}
