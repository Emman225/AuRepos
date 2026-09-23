<?php

namespace App\Domain\Comptes\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Support\Api\ErreurMetier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * Connexion unique pour les douze profils : un seul point d'entrée, et
 * c'est le profil du compte qui oriente ensuite vers le bon espace.
 * (Mon Gravier avait cinq pages de connexion web et trois préfixes mobiles.)
 */
final class ConnexionService
{
    public function __construct(private readonly JournalAudit $journal) {}

    // Même phrase pour « compte inconnu » et « mauvais mot de passe » :
    // la réponse ne doit pas révéler quels comptes existent.
    private const REFUS = 'Identifiant ou mot de passe incorrect.';

    /**
     * @return array{utilisateur: User, jeton: string, expire_dans: int}
     *
     * @throws AuthenticationException
     * @throws ErreurMetier
     */
    public function connecter(string $identifiant, string $motDePasse): array
    {
        $identifiant = trim($identifiant);

        $utilisateur = User::query()
            ->where(fn ($q) => $q
                ->whereRaw('lower(email) = ?', [mb_strtolower($identifiant)])
                ->orWhere('identifiant', $identifiant)
                ->orWhere('telephone', $identifiant))
            ->first();

        if ($utilisateur !== null && ! Hash::check($motDePasse, $utilisateur->password)) {
            // Trace des essais ratés sur un compte réel : c'est ainsi qu'on repère une attaque.
            $this->journal->consigner('connexion_refusee', 'Mot de passe incorrect : '.$utilisateur->libelleAudit().'.', $utilisateur, auteur: $utilisateur);
        }

        if ($utilisateur === null || ! Hash::check($motDePasse, $utilisateur->password)) {
            throw new AuthenticationException(self::REFUS);
        }

        // Le mot de passe est bon : on peut dire au client ce qui lui manque.
        if ($utilisateur->statut === StatutCompte::EnAttente && $utilisateur->email_verified_at === null) {
            throw ErreurMetier::courrielNonVerifie();
        }

        if (! $utilisateur->statut->peutSeConnecter()) {
            throw new AuthenticationException(
                'Ce compte ne peut pas se connecter pour le moment. Contactez l’entreprise.',
            );
        }

        $utilisateur->forceFill(['derniere_connexion_le' => now()])->saveQuietly();
        $this->journal->consigner('connexion', 'Connexion : '.$utilisateur->libelleAudit().'.', $utilisateur, auteur: $utilisateur);

        return $this->ouvrirPour($utilisateur);
    }

    /** @return array{utilisateur: User, jeton: string, expire_dans: int} */
    public function rafraichir(): array
    {
        $garde = $this->garde();
        // L'ancien jeton part en liste noire : il ne resservira pas.
        $jeton = $garde->refresh();
        /** @var User $utilisateur */
        $utilisateur = $garde->setToken($jeton)->user();

        return ['utilisateur' => $utilisateur, 'jeton' => $jeton, 'expire_dans' => $this->dureeEnSecondes()];
    }

    public function deconnecter(): void
    {
        $this->garde()->logout();
    }

    /**
     * Ouvre une session pour un compte déjà authentifié par ailleurs (code de vérification).
     *
     * @return array{utilisateur: User, jeton: string, expire_dans: int}
     */
    public function ouvrirPour(User $utilisateur): array
    {
        return [
            'utilisateur' => $utilisateur,
            'jeton' => $this->garde()->login($utilisateur),
            'expire_dans' => $this->dureeEnSecondes(),
        ];
    }

    private function dureeEnSecondes(): int
    {
        return (int) config('jwt.ttl') * 60;
    }

    private function garde(): JWTGuard
    {
        /** @var JWTGuard $garde */
        $garde = auth('api');

        return $garde;
    }
}
