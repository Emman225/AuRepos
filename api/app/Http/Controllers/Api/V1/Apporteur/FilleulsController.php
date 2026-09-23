<?php

namespace App\Http\Controllers\Api\V1\Apporteur;

use App\Http\Controllers\Api\V1\Apporteur\Concerns\ResoutLApporteurConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Apporteur\FilleulResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace apporteur › SES filleuls (lecture seule) : rien de sensible, ni courriel ni téléphone. */
final class FilleulsController extends Controller
{
    use ResoutLApporteurConnecte;

    public function index(Request $request): JsonResponse
    {
        $apporteur = $this->monApporteur($request);

        return ReponseApi::succes(FilleulResource::collection(
            $apporteur->filleuls()->orderByDesc('created_at')->get(),
        ));
    }
}
