<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Mail\BienvenuePartenaireMail;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Le client d'une réservation manuelle (CdC § 5.2, § 6.1) : un client déjà connu de la
 * plateforme, retrouvé par courriel — jamais dupliqué —, ou un compte créé pour lui par
 * la réception, comme pour un partenaire : mot de passe aléatoire inconnu, invitation à
 * le choisir lui-même.
 */
final class ClientDeLaReception
{
    /** @param array{nom?: string, prenoms?: string|null, email?: string, telephone?: string|null}|null $nouveauClient */
    public function resoudre(?int $clientId, ?array $nouveauClient): User
    {
        if ($clientId !== null) {
            return User::query()->whereKey($clientId)->where('profil', Profil::Client)->firstOrFail();
        }

        if ($nouveauClient === null || blank($nouveauClient['email'] ?? null)) {
            throw new ErreurMetier('Indiquez un client existant ou le courriel du nouveau client.', 'client_absent', 422);
        }

        $email = mb_strtolower($nouveauClient['email']);

        // On ne duplique jamais un client déjà connu : c'est le compte existant qui reçoit la réservation.
        $existant = User::query()->where('profil', Profil::Client)->whereRaw('lower(email) = ?', [$email])->first();
        if ($existant !== null) {
            return $existant;
        }

        $client = User::create([
            'nom' => $nouveauClient['nom'] ?? '', 'prenoms' => $nouveauClient['prenoms'] ?? null,
            'email' => $email, 'telephone' => $nouveauClient['telephone'] ?? null,
            // Mot de passe aléatoire que personne ne connaît : le client le remplacera lui-même.
            'password' => Str::password(40), 'profil' => Profil::Client, 'statut' => StatutCompte::Actif,
        ]);
        $client->forceFill(['email_verified_at' => now()])->saveQuietly();

        try {
            Mail::to($client->email)->send(new BienvenuePartenaireMail($client));
        } catch (Throwable $e) {
            // Un envoi manqué ne bloque jamais l'opération (CdC § 13.2).
            Log::error('Courriel de bienvenue (client réception) non envoyé', ['user_id' => $client->id, 'erreur' => $e->getMessage()]);
        }

        return $client;
    }
}
