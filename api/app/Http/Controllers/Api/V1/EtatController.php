<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Point de contrôle : l'API répond-elle, et la base aussi ?
 * Sert au déploiement, à la supervision et au premier appel des clients.
 */
final class EtatController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $base = true;
        } catch (Throwable) {
            $base = false;
        }

        return ReponseApi::succes([
            'application' => config('app.name'),
            'version_api' => 'v1',
            'base_de_donnees' => $base,
            'heure_serveur' => now()->toIso8601String(),
        ], $base ? "L'API est en service." : "L'API répond, mais la base de données est injoignable.");
    }
}
