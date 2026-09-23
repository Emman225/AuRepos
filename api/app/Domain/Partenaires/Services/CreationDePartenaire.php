<?php

namespace App\Domain\Partenaires\Services;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Repas\Models\Livreur;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Transferts\Models\Chauffeur;
use App\Mail\BienvenuePartenaireMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Création d'un compte partenaire par le back office : le compte de connexion et
 * la fiche naissent ensemble, ou pas du tout.
 *
 * Aucun mot de passe n'est choisi ni transmis par le personnel : le partenaire
 * le définit lui-même par « Mot de passe oublié », comme l'y invite son courriel de bienvenue.
 */
final class CreationDePartenaire
{
    /**
     * @param  array<string, mixed>  $compte  nom, prenoms, email, telephone
     * @param  array<string, mixed>  $fiche  champs de la fiche propriétaire
     */
    public function proprietaire(array $compte, array $fiche): Proprietaire
    {
        $proprietaire = DB::transaction(function () use ($compte, $fiche): Proprietaire {
            $utilisateur = User::create([
                'nom' => $compte['nom'],
                'prenoms' => $compte['prenoms'] ?? null,
                'email' => mb_strtolower($compte['email']),
                'telephone' => $compte['telephone'] ?? null,
                // Mot de passe aléatoire que personne ne connaît : il sera remplacé par le partenaire.
                'password' => Str::password(40),
                'profil' => Profil::Proprietaire,
                'statut' => StatutCompte::Actif,
            ]);
            $utilisateur->forceFill(['email_verified_at' => now()])->saveQuietly();

            return Proprietaire::create([...$fiche, 'user_id' => $utilisateur->id]);
        });

        try {
            Mail::to($proprietaire->utilisateur->email)->send(new BienvenuePartenaireMail($proprietaire->utilisateur));
        } catch (Throwable $e) {
            // Un envoi manqué ne bloque jamais l'opération (CdC § 13.2).
            Log::error('Courriel de bienvenue non envoyé', ['user_id' => $proprietaire->user_id, 'erreur' => $e->getMessage()]);
        }

        return $proprietaire;
    }

    /**
     * @param  array<string, mixed>  $compte  nom, prenoms, email, telephone
     * @param  array<string, mixed>  $fiche  champs de la fiche apporteur (pourcentage, actif, code?)
     */
    public function apporteur(array $compte, array $fiche): Apporteur
    {
        $apporteur = DB::transaction(function () use ($compte, $fiche): Apporteur {
            $utilisateur = User::create([
                'nom' => $compte['nom'],
                'prenoms' => $compte['prenoms'] ?? null,
                'email' => mb_strtolower($compte['email']),
                'telephone' => $compte['telephone'] ?? null,
                // Mot de passe aléatoire que personne ne connaît : il sera remplacé par le partenaire.
                'password' => Str::password(40),
                'profil' => Profil::Apporteur,
                'statut' => StatutCompte::Actif,
            ]);
            $utilisateur->forceFill(['email_verified_at' => now()])->saveQuietly();

            return Apporteur::create([...$fiche, 'user_id' => $utilisateur->id]);
        });

        try {
            Mail::to($apporteur->utilisateur->email)->send(new BienvenuePartenaireMail($apporteur->utilisateur));
        } catch (Throwable $e) {
            // Un envoi manqué ne bloque jamais l'opération (CdC § 13.2).
            Log::error('Courriel de bienvenue non envoyé', ['user_id' => $apporteur->user_id, 'erreur' => $e->getMessage()]);
        }

        return $apporteur;
    }

    /**
     * @param  array<string, mixed>  $compte  nom, prenoms, email, telephone
     * @param  array<string, mixed>  $fiche  champs de la fiche chauffeur (actif?)
     */
    public function chauffeur(array $compte, array $fiche): Chauffeur
    {
        $chauffeur = DB::transaction(function () use ($compte, $fiche): Chauffeur {
            $utilisateur = User::create([
                'nom' => $compte['nom'],
                'prenoms' => $compte['prenoms'] ?? null,
                'email' => mb_strtolower($compte['email']),
                'telephone' => $compte['telephone'] ?? null,
                // Mot de passe aléatoire que personne ne connaît : il sera remplacé par le partenaire.
                'password' => Str::password(40),
                'profil' => Profil::Chauffeur,
                'statut' => StatutCompte::Actif,
            ]);
            $utilisateur->forceFill(['email_verified_at' => now()])->saveQuietly();

            return Chauffeur::create([...$fiche, 'user_id' => $utilisateur->id]);
        });

        try {
            Mail::to($chauffeur->utilisateur->email)->send(new BienvenuePartenaireMail($chauffeur->utilisateur));
        } catch (Throwable $e) {
            // Un envoi manqué ne bloque jamais l'opération (CdC § 13.2).
            Log::error('Courriel de bienvenue non envoyé', ['user_id' => $chauffeur->user_id, 'erreur' => $e->getMessage()]);
        }

        return $chauffeur;
    }

    /**
     * @param  array<string, mixed>  $compte  nom, prenoms, email, telephone
     * @param  array<string, mixed>  $fiche  champs de la fiche restaurateur (assujetti_tva, actif?)
     */
    public function restaurateur(array $compte, array $fiche): Restaurateur
    {
        $restaurateur = DB::transaction(function () use ($compte, $fiche): Restaurateur {
            $utilisateur = User::create([
                'nom' => $compte['nom'],
                'prenoms' => $compte['prenoms'] ?? null,
                'email' => mb_strtolower($compte['email']),
                'telephone' => $compte['telephone'] ?? null,
                // Mot de passe aléatoire que personne ne connaît : il sera remplacé par le partenaire.
                'password' => Str::password(40),
                'profil' => Profil::Restaurateur,
                'statut' => StatutCompte::Actif,
            ]);
            $utilisateur->forceFill(['email_verified_at' => now()])->saveQuietly();

            return Restaurateur::create([...$fiche, 'user_id' => $utilisateur->id]);
        });

        try {
            Mail::to($restaurateur->utilisateur->email)->send(new BienvenuePartenaireMail($restaurateur->utilisateur));
        } catch (Throwable $e) {
            // Un envoi manqué ne bloque jamais l'opération (CdC § 13.2).
            Log::error('Courriel de bienvenue non envoyé', ['user_id' => $restaurateur->user_id, 'erreur' => $e->getMessage()]);
        }

        return $restaurateur;
    }

    /**
     * @param  array<string, mixed>  $compte  nom, prenoms, email, telephone
     * @param  array<string, mixed>  $fiche  champs de la fiche livreur (actif?)
     */
    public function livreur(array $compte, array $fiche): Livreur
    {
        $livreur = DB::transaction(function () use ($compte, $fiche): Livreur {
            $utilisateur = User::create([
                'nom' => $compte['nom'],
                'prenoms' => $compte['prenoms'] ?? null,
                'email' => mb_strtolower($compte['email']),
                'telephone' => $compte['telephone'] ?? null,
                // Mot de passe aléatoire que personne ne connaît : il sera remplacé par le partenaire.
                'password' => Str::password(40),
                'profil' => Profil::Livreur,
                'statut' => StatutCompte::Actif,
            ]);
            $utilisateur->forceFill(['email_verified_at' => now()])->saveQuietly();

            return Livreur::create([...$fiche, 'user_id' => $utilisateur->id]);
        });

        try {
            Mail::to($livreur->utilisateur->email)->send(new BienvenuePartenaireMail($livreur->utilisateur));
        } catch (Throwable $e) {
            // Un envoi manqué ne bloque jamais l'opération (CdC § 13.2).
            Log::error('Courriel de bienvenue non envoyé', ['user_id' => $livreur->user_id, 'erreur' => $e->getMessage()]);
        }

        return $livreur;
    }
}
