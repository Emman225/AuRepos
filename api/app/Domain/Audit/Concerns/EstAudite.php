<?php

namespace App\Domain\Audit\Concerns;

use App\Domain\Audit\Services\JournalAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * À poser sur tout modèle dont les changements doivent laisser une trace :
 * création, modification (avant / après des seuls champs changés), suppression.
 *
 * Le modèle peut définir `libelleAudit(): string` pour se nommer dans le récit.
 *
 * @mixin Model
 */
trait EstAudite
{
    public static function bootEstAudite(): void
    {
        static::created(function (Model $modele): void {
            app(JournalAudit::class)->consigner(
                'creation',
                'Création : '.JournalAudit::libelleDe($modele).'.',
                $modele,
                apres: self::valeursTypees($modele),
            );
        });

        static::updated(function (Model $modele): void {
            // Valeurs TYPÉES des deux côtés (énumérations, JSON, dates) : sans cela l'avant
            // d'un champ JSON vaut 500 et son après "0", ce qui rend le journal illisible.
            $avant = $apres = [];
            foreach (array_keys($modele->getChanges()) as $champ) {
                $avant[$champ] = $modele->getOriginal($champ);
                $apres[$champ] = $modele->getAttribute($champ);
            }

            $lisibles = JournalAudit::nettoyer($apres);
            if ($lisibles === []) {
                return; // seuls des champs techniques ont bougé
            }

            app(JournalAudit::class)->consigner(
                'modification',
                'Modification : '.JournalAudit::libelleDe($modele).' — '.implode(', ', array_keys($lisibles)).'.',
                $modele,
                $avant,
                $apres,
            );
        });

        static::deleted(function (Model $modele): void {
            app(JournalAudit::class)->consigner(
                'suppression',
                'Suppression : '.JournalAudit::libelleDe($modele).'.',
                $modele,
                avant: self::valeursTypees($modele),
            );
        });
    }

    /** @return array<string, mixed> */
    private static function valeursTypees(Model $modele): array
    {
        $valeurs = [];
        foreach (array_keys($modele->getAttributes()) as $champ) {
            $valeurs[$champ] = $modele->getAttribute($champ);
        }

        return $valeurs;
    }
}
