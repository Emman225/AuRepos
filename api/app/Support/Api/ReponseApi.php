<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Enveloppe unique de toutes les réponses de l'API :
 *
 *   { "success": true|false, "message": "…", "data": …, "errors": … }
 *
 * Mon Gravier répondait 200 à tout et glissait le vrai code dans le corps ;
 * ici le code HTTP dit la vérité et l'enveloppe ne fait que la détailler,
 * pour que React (TanStack Query) et Flutter (dio) traitent les erreurs
 * de la même façon, sans lire le corps pour savoir si l'appel a réussi.
 */
final class ReponseApi
{
    public static function succes(mixed $data = null, string $message = 'Opération réussie.', int $statut = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $statut);
    }

    /**
     * Page d'une liste : les éléments passent par leur Resource, la pagination suit.
     *
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @param  class-string<JsonResource>  $ressource
     */
    public static function page(LengthAwarePaginator $page, string $ressource, string $message = 'Liste chargée.'): JsonResponse
    {
        return self::succes([
            'elements' => $ressource::collection($page->items()),
            'pagination' => [
                'page' => $page->currentPage(),
                'par_page' => $page->perPage(),
                'total' => $page->total(),
                'derniere_page' => $page->lastPage(),
            ],
        ], $message);
    }

    public static function cree(mixed $data = null, string $message = 'Enregistrement créé.'): JsonResponse
    {
        return self::succes($data, $message, 201);
    }

    /**
     * @param  array<string, array<int, string>>|null  $erreurs  détail par champ (validation) ou null
     */
    public static function echec(string $message, int $statut, ?array $erreurs = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $erreurs,
        ], $statut);
    }
}
