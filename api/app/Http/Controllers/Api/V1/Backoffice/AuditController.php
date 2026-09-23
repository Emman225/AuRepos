<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Audit\Models\EntreeAudit;
use App\Http\Controllers\Controller;
use App\Http\Resources\EntreeAuditResource;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consultation du journal d'audit — réservée aux administrateurs. Aucune écriture possible. */
final class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'user_id' => ['nullable', 'integer'],
            'sujet_type' => ['nullable', 'string', 'max:80'],
            'sujet_id' => ['nullable', 'integer'],
            'recherche' => ['nullable', 'string', 'max:100'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $requete = EntreeAudit::query()
            ->when($filtres['action'] ?? null, fn (Builder $q, string $v) => $q->where('action', $v))
            ->when($filtres['user_id'] ?? null, fn (Builder $q, int $v) => $q->where('user_id', $v))
            ->when($filtres['sujet_type'] ?? null, fn (Builder $q, string $v) => $q->where('sujet_type', $v))
            ->when($filtres['sujet_id'] ?? null, fn (Builder $q, int $v) => $q->where('sujet_id', $v))
            ->when($filtres['recherche'] ?? null, fn (Builder $q, string $v) => $q->where('recit', 'ilike', '%'.addcslashes($v, '%_\\').'%'));

        $page = FiltrePeriode::depuis($request)->appliquer($requete, 'cree_le')
            ->orderByDesc('id')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, EntreeAuditResource::class);
    }
}
