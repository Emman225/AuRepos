<?php

namespace App\Console\Commands;

use App\Domain\Partenaires\Services\RelevesProprietaires;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Génération planifiée des relevés propriétaires (P3-PRO-03, CdC § 7.2) : le jour paramétrable
 * `proprietaires.jour_releves`, pour le MOIS PRÉCÉDENT (le mois en cours n'est pas terminé, ses
 * nuitées ne sont pas toutes consommées). Envoi par courriel dans la foulée.
 */
class GenererLesRelevesProprietaires extends Command
{
    protected $signature = 'proprietaires:generer-releves {--mois= : Mois à générer, format AAAA-MM (par défaut le mois précédent)}';

    protected $description = 'Génère et envoie les relevés mensuels des propriétaires (CdC § 7.2)';

    public function handle(RelevesProprietaires $releves): int
    {
        $mois = $this->option('mois')
            ? Carbon::createFromFormat('Y-m', (string) $this->option('mois'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $genere = $releves->genererPourLeMoisTousProprietaires($mois);

        $envoyes = 0;
        foreach ($genere as $releve) {
            if ($releves->envoyerUneFois($releve)) {
                $envoyes++;
            }
        }

        $this->info(sprintf('%d relevé(s) généré(s) pour %s, %d envoyé(s).', count($genere), $mois->format('m/Y'), $envoyes));

        return self::SUCCESS;
    }
}
