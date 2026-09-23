<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tâches planifiées. Sur le serveur, UNE seule ligne de cron suffit :
|   * * * * * cd /chemin/vers/api && php artisan schedule:run >> /dev/null 2>&1
*/

// Une demande non réglée libère ses dates passé le délai (CdC § 5.2). withoutOverlapping : jamais deux passes en même temps.
Schedule::command('sejours:expirer-demandes')->everyFiveMinutes()->withoutOverlapping();

// Aucun paiement en ligne ne reste en suspens : on redemande son état à la passerelle (CdC § 8.3).
Schedule::command('paiements:reprendre')->everyFiveMinutes()->withoutOverlapping();

// Aucun courriel, SMS ou message WhatsApp ne reste sans suite (CdC § 13.2).
Schedule::command('notifications:reprendre')->everyFiveMinutes()->withoutOverlapping();

// No-show automatique à J+1 de l'arrivée, heure paramétrable (CdC § 4, P2-SEJ-05).
Schedule::command('sejours:traiter-no-show')->hourly()->withoutOverlapping();
