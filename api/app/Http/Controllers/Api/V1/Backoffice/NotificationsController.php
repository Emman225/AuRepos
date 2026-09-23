<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Notifications\Enums\CanalNotification;
use App\Domain\Notifications\Enums\EtatNotification;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Services\Notificateur;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\NotificationResource;
use App\Support\Api\ErreurMetier;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Journal des notifications (CdC § 13.2) : consultation et relance manuelle. */
final class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'canal' => ['nullable', Rule::enum(CanalNotification::class)],
            'etat' => ['nullable', Rule::enum(EtatNotification::class)],
            'destinataire' => ['nullable', 'string', 'max:255'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $requete = Notification::query()
            ->when($filtres['canal'] ?? null, fn (Builder $q, string $v) => $q->where('canal', $v))
            ->when($filtres['etat'] ?? null, fn (Builder $q, string $v) => $q->where('etat', $v))
            ->when($filtres['destinataire'] ?? null, fn (Builder $q, string $v) => $q->where('destinataire', $v));

        $page = FiltrePeriode::depuis($request)->appliquer($requete)->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, NotificationResource::class);
    }

    /** Relance manuelle, immédiate — sans attendre la reprise planifiée. */
    public function relancer(Notification $notification, Notificateur $notificateur): JsonResponse
    {
        if ($notification->etat === EtatNotification::Envoyee) {
            throw new ErreurMetier('Cette notification est déjà envoyée.', 'notification_deja_envoyee', 422);
        }

        $notificateur->envoyer($notification);

        return ReponseApi::succes(new NotificationResource($notification->refresh()), 'Relance effectuée.');
    }
}
