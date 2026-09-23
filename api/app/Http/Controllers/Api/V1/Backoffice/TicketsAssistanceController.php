<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Assistance\Enums\EtatDuTicket;
use App\Domain\Assistance\Models\TicketAssistance;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assistance\TicketAssistanceResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * File des tickets d'assistance (P2-BO-03, CdC § 6.1), en LECTURE côté back office —
 * l'instruction (répondre, fermer) revient à l'espace assistance (routes/api_v1/assistance.php).
 */
final class TicketsAssistanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'statut' => ['nullable', Rule::enum(EtatDuTicket::class)],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = TicketAssistance::query()->with(['sejour.logement.residence', 'client'])
            ->when($filtres['statut'] ?? null, fn (Builder $q, string $v) => $q->where('statut', $v))
            ->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, TicketAssistanceResource::class);
    }
}
