<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Comptes\Models\User;
use App\Domain\Comptes\Services\CodesDeVerification;
use App\Domain\Comptes\Services\InscriptionService;
use App\Domain\Comptes\Services\MotDePasseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CourrielRequest;
use App\Http\Requests\Auth\InscriptionRequest;
use App\Http\Requests\Auth\ReinitialisationRequest;
use App\Http\Requests\Auth\VerificationRequest;
use App\Http\Resources\UtilisateurResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/** Inscription des clients, vérification du courriel, mot de passe oublié. */
final class InscriptionController extends Controller
{
    public function __construct(
        private readonly InscriptionService $inscription,
        private readonly MotDePasseService $motDePasse,
    ) {}

    public function inscrire(InscriptionRequest $request): JsonResponse
    {
        /** @var array{nom: string, prenoms?: string|null, email: string, telephone?: string|null, mot_de_passe: string, code_parrain?: string|null} $saisie */
        $saisie = $request->validated();
        $utilisateur = $this->inscription->inscrire($saisie);

        return ReponseApi::cree(
            ['email' => $utilisateur->email, 'code_valable_minutes' => CodesDeVerification::DUREE_EN_MINUTES],
            'Compte créé. Saisissez le code à six chiffres envoyé à votre adresse.',
        );
    }

    public function verifier(VerificationRequest $request): JsonResponse
    {
        $session = $this->inscription->verifier(
            $this->compteOuRefus($request->string('email')->value()),
            $request->string('code')->value(),
        );

        return ReponseApi::succes([
            'jeton' => $session['jeton'],
            'type' => 'Bearer',
            'expire_dans' => $session['expire_dans'],
            'utilisateur' => new UtilisateurResource($session['utilisateur']->load('agence')),
        ], 'Adresse vérifiée. Bienvenue !');
    }

    public function renvoyerLeCode(CourrielRequest $request): JsonResponse
    {
        $utilisateur = $this->compte($request->string('email')->value());
        if ($utilisateur !== null) {
            $this->inscription->renvoyerLeCode($utilisateur);
        }

        // Même réponse que le compte existe ou non.
        return ReponseApi::succes(null, 'Si un compte attend sa vérification à cette adresse, un nouveau code vient de partir.');
    }

    public function motDePasseOublie(CourrielRequest $request): JsonResponse
    {
        $this->motDePasse->demanderUnCode($request->string('email')->value());

        return ReponseApi::succes(
            ['code_valable_minutes' => CodesDeVerification::DUREE_EN_MINUTES],
            'Si un compte existe à cette adresse, un code vient de lui être envoyé.',
        );
    }

    public function reinitialiser(ReinitialisationRequest $request): JsonResponse
    {
        $this->motDePasse->reinitialiser(
            $this->compteOuRefus($request->string('email')->value()),
            $request->string('code')->value(),
            $request->string('mot_de_passe')->value(),
        );

        return ReponseApi::succes(null, 'Mot de passe changé. Vous pouvez vous connecter.');
    }

    private function compte(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    /** Compte inconnu et mauvais code donnent la même erreur : on ne révèle pas quels comptes existent. */
    private function compteOuRefus(string $email): User
    {
        return $this->compte($email) ?? throw ValidationException::withMessages([
            'code' => 'Ce code est incorrect ou a expiré. Demandez-en un nouveau.',
        ]);
    }
}
