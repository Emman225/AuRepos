<?php

namespace App\Jobs;

use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Services\Notificateur;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envoie UNE notification déjà journalisée. On ne sérialise que son identifiant : entre la
 * mise en file et le passage du job, son état a pu changer (reprise planifiée, par exemple).
 */
final class EnvoyerNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $notificationId) {}

    public function handle(Notificateur $notificateur): void
    {
        $notification = Notification::find($this->notificationId);
        if ($notification !== null) {
            $notificateur->envoyer($notification);
        }
    }
}
