<?php

namespace App\Domain\Notifications\Passerelles;

use App\Domain\Notifications\Contracts\PasserelleDeMessage;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** WhatsApp via l'API Cloud de Meta (messages texte libres, fenêtre de conversation ouverte). */
final class WhatsAppCloudApi implements PasserelleDeMessage
{
    public function nom(): string
    {
        return 'whatsapp_cloud_api';
    }

    public function estConfiguree(): bool
    {
        return filled(config('notifications.whatsapp.numero_id')) && filled(config('notifications.whatsapp.jeton'));
    }

    public function envoyer(string $destinataire, string $corps): void
    {
        $numero = preg_replace('/[^0-9]/', '', $destinataire);

        $reponse = Http::withToken((string) config('notifications.whatsapp.jeton'))
            ->acceptJson()
            ->timeout((int) config('notifications.whatsapp.timeout'))
            ->post(rtrim((string) config('notifications.whatsapp.url'), '/').'/'.config('notifications.whatsapp.numero_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $numero,
                'type' => 'text',
                'text' => ['body' => Str::limit($corps, 4096, '')],
            ]);

        if ($reponse->failed()) {
            throw new ErreurMetier('Message WhatsApp refusé par la passerelle ('.$reponse->status().').', 'whatsapp_refuse', 502);
        }
    }
}
