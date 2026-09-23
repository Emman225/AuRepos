<?php

namespace App\Support\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Traduit toute exception levée sous /api en enveloppe ReponseApi.
 *
 * Règle de sécurité : le message d'une exception imprévue ne sort JAMAIS
 * vers le client (Mon Gravier renvoyait $th->getMessage(), donc des noms
 * de tables et de colonnes). Il part dans le journal ; le client reçoit
 * une phrase neutre.
 */
final class RenduDesExceptions
{
    public static function enregistrer(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return self::versReponse($e);
        });
    }

    private static function versReponse(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ErreurMetier => ReponseApi::echec(
                $e->getMessage(), $e->statut, ['code' => [$e->codeMetier]],
            ),
            $e instanceof ValidationException => ReponseApi::echec(
                'Certaines informations sont incorrectes.', 422, $e->errors(),
            ),
            // Le message d'une AuthenticationException est écrit par nous (connexion refusée,
            // compte bloqué) ; seul le message par défaut de Laravel, en anglais, est remplacé.
            $e instanceof AuthenticationException => ReponseApi::echec(
                $e->getMessage() === 'Unauthenticated.' ? 'Vous devez vous connecter.' : $e->getMessage(), 401,
            ),
            // Jeton absent, expiré, falsifié ou déjà révoqué.
            $e instanceof JWTException => ReponseApi::echec(
                'Votre session a expiré. Reconnectez-vous.', 401,
            ),
            $e instanceof AuthorizationException => ReponseApi::echec(
                'Cet écran ne relève pas de votre profil.', 403,
            ),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ReponseApi::echec(
                'Élément introuvable.', 404,
            ),
            $e instanceof ThrottleRequestsException => ReponseApi::echec(
                'Trop de demandes. Patientez un instant puis réessayez.', 429,
            ),
            $e instanceof HttpExceptionInterface => ReponseApi::echec(
                self::messageHttp($e->getStatusCode()), $e->getStatusCode(),
            ),
            default => ReponseApi::echec(
                'Une erreur technique est survenue. Elle a été enregistrée.', 500,
            ),
        };
    }

    private static function messageHttp(int $statut): string
    {
        return match ($statut) {
            401 => 'Vous devez vous connecter.',
            403 => 'Cet écran ne relève pas de votre profil.',
            404 => 'Élément introuvable.',
            405 => 'Méthode non autorisée pour cette adresse.',
            503 => 'Le site est en construction. Revenez dans un instant.',
            default => 'La demande ne peut pas aboutir.',
        };
    }
}
