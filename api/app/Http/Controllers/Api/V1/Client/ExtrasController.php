<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Extras\Models\Extra;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ExtraResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;

/** Mon espace › le catalogue des extras proposés (P2-EXT-01), avant de commander. */
final class ExtrasController extends Controller
{
    public function index(): JsonResponse
    {
        return ReponseApi::succes(ExtraResource::collection(Extra::query()->where('actif', true)->orderBy('nom')->get()));
    }
}
