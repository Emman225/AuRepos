<?php

namespace App\Domain\Comptes\Services;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Enums\UsageDuCode;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Services\Parrainage;
use Illuminate\Support\Facades\DB;

/**
 * Inscription en ligne : réservée au profil Client. Les autres comptes sont
 * créés par un administrateur (personnel) ou validés par lui (partenaires).
 */
final class InscriptionService
{
    public function __construct(
        private readonly CodesDeVerification $codes,
        private readonly ConnexionService $connexion,
        private readonly Parrainage $parrainage,
    ) {}

    /**
     * @param  array{nom: string, prenoms?: string|null, email: string, telephone?: string|null, mot_de_passe: string, code_parrain?: string|null}  $saisie
     */
    public function inscrire(array $saisie): User
    {
        $utilisateur = DB::transaction(function () use ($saisie): User {
            $utilisateur = User::create([
                'nom' => $saisie['nom'],
                'prenoms' => $saisie['prenoms'] ?? null,
                'email' => mb_strtolower($saisie['email']),
                'telephone' => $saisie['telephone'] ?? null,
                'password' => $saisie['mot_de_passe'],
                // Le profil ne vient JAMAIS de la saisie : personne ne s'inscrit administrateur.
                'profil' => Profil::Client,
                'statut' => StatutCompte::EnAttente,
            ]);

            // Code de parrainage facultatif : un code inconnu ou un apporteur inactif fait
            // échouer l'inscription (422) — la transaction rend alors le compte tout juste créé.
            if (! blank($saisie['code_parrain'] ?? null)) {
                $this->parrainage->inscrireAvecCode($utilisateur, $saisie['code_parrain']);
            }

            return $utilisateur;
        });

        $this->codes->envoyer($utilisateur, UsageDuCode::VerificationCourriel);

        return $utilisateur;
    }

    /**
     * Le bon code active le compte et ouvre la session dans la foulée.
     *
     * @return array{utilisateur: User, jeton: string, expire_dans: int}
     */
    public function verifier(User $utilisateur, string $code): array
    {
        $this->codes->consommer($utilisateur, UsageDuCode::VerificationCourriel, $code);

        $utilisateur->forceFill([
            'statut' => StatutCompte::Actif,
            'email_verified_at' => now(),
        ])->save();

        return $this->connexion->ouvrirPour($utilisateur);
    }

    public function renvoyerLeCode(User $utilisateur): void
    {
        if ($utilisateur->statut === StatutCompte::EnAttente) {
            $this->codes->envoyer($utilisateur, UsageDuCode::VerificationCourriel);
        }
    }
}
