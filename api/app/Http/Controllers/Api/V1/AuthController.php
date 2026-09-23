<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Comptes\Models\User;
use App\Domain\Comptes\Services\ConnexionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Http\Resources\UtilisateurResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(private readonly ConnexionService $connexion) {}

    public function connexion(ConnexionRequest $request): JsonResponse
    {
        $session = $this->connexion->connecter(
            $request->string('identifiant')->value(),
            $request->string('mot_de_passe')->value(),
        );

        return ReponseApi::succes($this->presenter($session), 'Connexion réussie.');
    }

    public function moi(Request $request): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return ReponseApi::succes(new UtilisateurResource($utilisateur->load('agence')));
    }

    public function rafraichir(): JsonResponse
    {
        return ReponseApi::succes($this->presenter($this->connexion->rafraichir()), 'Session prolongée.');
    }

    public function deconnexion(): JsonResponse
    {
        $this->connexion->deconnecter();

        return ReponseApi::succes(null, 'Vous êtes déconnecté.');
    }

    /**
     * @param  array{utilisateur: User, jeton: string, expire_dans: int}  $session
     * @return array<string, mixed>
     */
    private function presenter(array $session): array
    {
        return [
            'jeton' => $session['jeton'],
            'type' => 'Bearer',
            'expire_dans' => $session['expire_dans'],
            'utilisateur' => new UtilisateurResource($session['utilisateur']->load('agence')),
        ];
    }
}
