<?php

namespace App\Domain\Parametres\Services;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Models\Parametre;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Lecture et écriture des paramètres de la plateforme.
 *
 *   app(Parametres::class)->valeur('taxes.tva')   // 18.0
 *
 * Une valeur lue ici sert aux NOUVELLES opérations : taux et prix sont figés
 * sur chaque séjour à la réservation, un changement ne touche pas l'existant.
 */
final class Parametres
{
    private const CLE_DE_CACHE = 'parametres.valeurs';

    /** @var array<string, mixed>|null */
    private ?array $valeurs = null;

    /**
     * @return array{libelle: string, type: string, defaut: mixed, regles: list<string>, onglet: string, options?: array<string, string>, aide?: string, public?: bool, reserve?: bool}|null
     */
    public function definition(string $cle): ?array
    {
        foreach ((array) config('parametres') as $onglet => $contenu) {
            if (isset($contenu['parametres'][$cle])) {
                return [...$contenu['parametres'][$cle], 'onglet' => (string) $onglet];
            }
        }

        return null;
    }

    public function valeur(string $cle): mixed
    {
        $definition = $this->definition($cle) ?? throw new InvalidArgumentException("Paramètre inconnu : {$cle}");
        $valeurs = $this->toutesLesValeurs();

        return $this->typer(array_key_exists($cle, $valeurs) ? $valeurs[$cle] : $definition['defaut'], $definition['type']);
    }

    /** Sans trésorier désigné, aucune réduction ni geste commercial n'est possible (CdC § 6.1). */
    public function tresorierDesigne(): bool
    {
        return $this->valeur('gestionnaires.validant_2_id') !== null;
    }

    /**
     * Tous les onglets avec leurs valeurs courantes, pour l'écran Paramètres.
     *
     * @return list<array<string, mixed>>
     */
    public function onglets(): array
    {
        $onglets = [];
        foreach ((array) config('parametres') as $code => $contenu) {
            $parametres = [];
            foreach ($contenu['parametres'] as $cle => $definition) {
                $parametres[] = [
                    'cle' => $cle,
                    'nom' => $this->nomCourt($cle),
                    'libelle' => $definition['libelle'],
                    'type' => $definition['type'],
                    'valeur' => $this->valeur($cle),
                    'defaut' => $definition['defaut'],
                    'options' => $definition['options'] ?? null,
                    'aide' => $definition['aide'] ?? null,
                    'reserve_super_administrateur' => $definition['reserve'] ?? false,
                    'double_validation' => $definition['double_validation'] ?? false,
                ];
            }
            $onglets[] = ['code' => $code, 'libelle' => $contenu['libelle'], 'parametres' => $parametres];
        }

        return $onglets;
    }

    /**
     * Sous-ensemble lisible sans connexion (devise, horaires, site en construction…).
     *
     * @return array<string, mixed>
     */
    public function publics(): array
    {
        $publics = [];
        foreach ((array) config('parametres') as $contenu) {
            foreach ($contenu['parametres'] as $cle => $definition) {
                if ($definition['public'] ?? false) {
                    $publics[$cle] = $this->valeur($cle);
                }
            }
        }

        return $publics;
    }

    /**
     * Enregistre un onglet. `$saisie` porte les noms courts : ['tva' => 18, 'tdt' => 3].
     * Seuls les paramètres présents dans la saisie sont touchés.
     *
     * @param  array<string, mixed>  $saisie
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function enregistrer(string $onglet, array $saisie, User $auteur): void
    {
        $definitions = config("parametres.{$onglet}.parametres");
        if (! is_array($definitions)) {
            throw ValidationException::withMessages(['onglet' => 'Onglet de paramètres inconnu.']);
        }

        $regles = $libelles = $cles = [];
        foreach ($definitions as $cle => $definition) {
            $nom = $this->nomCourt($cle);
            if (! array_key_exists($nom, $saisie)) {
                continue;
            }
            if (($definition['reserve'] ?? false) && $auteur->profil !== Profil::SuperAdministrateur) {
                throw new AuthorizationException;
            }
            if ($definition['double_validation'] ?? false) {
                throw ValidationException::withMessages([
                    "valeurs.{$nom}" => 'Ce réglage entre en vigueur après validation par un second administrateur : proposez-le depuis son propre écran.',
                ]);
            }
            $cles[$nom] = $cle;
            $libelles["valeurs.{$nom}"] = mb_strtolower($definition['libelle']);
            $regles["valeurs.{$nom}"] = $definition['type'] === 'administrateur'
                ? [...$definition['regles'], $this->regleAdministrateur()]
                : $definition['regles'];
        }

        // Les règles croisées (validant 2 ≠ validant 1, maximum ≥ minimum) doivent voir aussi
        // les valeurs déjà enregistrées, même quand un seul des deux champs est envoyé.
        $courantes = [];
        foreach (array_keys($definitions) as $cle) {
            $courantes[$this->nomCourt($cle)] = $this->valeur($cle);
        }

        $valides = Validator::make(['valeurs' => [...$courantes, ...$saisie]], $regles, [], $libelles)->validate()['valeurs'] ?? [];

        DB::transaction(function () use ($valides, $cles, $definitions, $auteur): void {
            foreach ($valides as $nom => $valeur) {
                $cle = $cles[$nom];
                Parametre::updateOrCreate(
                    ['cle' => $cle],
                    ['valeur' => $this->typer($valeur, $definitions[$cle]['type']), 'modifie_par' => $auteur->id],
                );
            }
        });

        $this->oublier();
    }

    public function oublier(): void
    {
        Cache::forget(self::CLE_DE_CACHE);
        $this->valeurs = null;
    }

    /**
     * La ligne `Parametre` d'une clé, créée avec sa valeur courante si elle n'existe pas encore
     * (CdC § 7.3 : un réglage à double validation doit avoir un « sujet » sur lequel proposer).
     */
    public function parametreModele(string $cle): Parametre
    {
        $definition = $this->definition($cle) ?? throw new InvalidArgumentException("Paramètre inconnu : {$cle}");

        return Parametre::query()->firstOrCreate(['cle' => $cle], ['valeur' => $this->valeur($cle) ?? $definition['defaut']]);
    }

    /** @return array<string, mixed> */
    private function toutesLesValeurs(): array
    {
        return $this->valeurs ??= Cache::rememberForever(
            self::CLE_DE_CACHE,
            fn (): array => Parametre::query()->pluck('valeur', 'cle')->all(),
        );
    }

    private function typer(mixed $valeur, string $type): mixed
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        return match ($type) {
            'entier', 'administrateur' => (int) $valeur,
            'decimal' => (float) $valeur,
            'booleen' => filter_var($valeur, FILTER_VALIDATE_BOOLEAN),
            default => (string) $valeur,
        };
    }

    /** 'taxes.tva' → 'tva' */
    private function nomCourt(string $cle): string
    {
        return substr($cle, (int) strpos($cle, '.') + 1);
    }

    /** Un validant désigné doit être un administrateur actif (désignation nominative, CdC § 3). */
    private function regleAdministrateur(): \Closure
    {
        return function (string $attribut, mixed $valeur, \Closure $echec): void {
            if ($valeur === null) {
                return;
            }
            $existe = User::query()->whereKey($valeur)
                ->whereIn('profil', [Profil::SuperAdministrateur, Profil::Administrateur])
                ->where('statut', StatutCompte::Actif)
                ->exists();

            if (! $existe) {
                $echec('La personne désignée doit être un administrateur actif.');
            }
        };
    }
}
