<?php

namespace App\Domain\Comptes\Services;

use App\Domain\Comptes\Enums\UsageDuCode;
use App\Domain\Comptes\Models\User;

final class MotDePasseService
{
    public function __construct(private readonly CodesDeVerification $codes) {}

    /**
     * Ne dit jamais si l'adresse correspond à un compte : la réponse est la
     * même dans tous les cas, seul un compte actif reçoit réellement un code.
     */
    public function demanderUnCode(string $email): void
    {
        $utilisateur = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();

        if ($utilisateur?->statut->peutSeConnecter()) {
            $this->codes->envoyer($utilisateur, UsageDuCode::ReinitialisationMotDePasse);
        }
    }

    public function reinitialiser(User $utilisateur, string $code, string $nouveauMotDePasse): void
    {
        $this->codes->consommer($utilisateur, UsageDuCode::ReinitialisationMotDePasse, $code);

        $this->changer($utilisateur, $nouveauMotDePasse);
    }

    /** Tout changement de mot de passe fait tomber les sessions ouvertes ailleurs. */
    public function changer(User $utilisateur, string $nouveauMotDePasse): void
    {
        $utilisateur->forceFill([
            'password' => $nouveauMotDePasse,
            'mot_de_passe_change_le' => now(),
        ])->save();
    }
}
