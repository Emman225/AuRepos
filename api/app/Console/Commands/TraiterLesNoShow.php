<?php

namespace App\Console\Commands;

use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\CycleDuSejour;
use Illuminate\Console\Command;

/**
 * No-show automatique (P2-SEJ-05, CdC § 4) : un séjour CONFIRMÉ, jamais arrivé, bascule en
 * no-show à J+1 de l'arrivée, à l'heure paramétrable `sejours.heure_no_show` — paramètre déjà
 * présent dans `config/parametres.php` (posé lors d'un lot précédent, jamais encore câblé à
 * aucune règle) : PAS un nouveau paramètre `delai_no_show_heures` à ajouter, il suffit de le
 * BRANCHER ici.
 */
class TraiterLesNoShow extends Command
{
    protected $signature = 'sejours:traiter-no-show';

    protected $description = 'Bascule en no-show les séjours confirmés jamais arrivés, passé le délai paramétrable (CdC § 4)';

    public function handle(CycleDuSejour $cycle, Parametres $parametres): int
    {
        $heure = (string) $parametres->valeur('sejours.heure_no_show');

        $candidats = Sejour::query()->where('etat', EtatDuSejour::Confirme->value)
            // J+1 de l'arrivée, à l'heure paramétrée : avant cet instant, pas encore de no-show.
            // date + entier = date (le lendemain) ; date + heure = horodatage, comparable à maintenant.
            ->whereRaw('((arrivee + 1) + ?::time) <= ?', [$heure, now()])
            ->get();

        $nombre = 0;
        foreach ($candidats as $sejour) {
            $cycle->declarerNoShow($sejour, 'No-show automatique : séjour confirmé jamais arrivé.');
            $nombre++;
        }

        $this->info($nombre === 0 ? 'Aucun no-show à déclarer.' : "{$nombre} séjour(s) basculé(s) en no-show.");

        return self::SUCCESS;
    }
}
