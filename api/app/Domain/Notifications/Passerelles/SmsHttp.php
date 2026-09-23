<?php

namespace App\Domain\Notifications\Passerelles;

use App\Domain\Notifications\Contracts\PasserelleDeMessage;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\Http;

/**
 * SMS via une passerelle HTTP générique (POST JSON). Le prestataire n'est pas encore choisi
 * (CdC : « développement neuf, absent de Mon Gravier » — décision D12) ; les noms de champs
 * sont donc paramétrables plutôt que codés en dur, pour s'adapter à celui qui sera retenu
 * sans toucher au code.
 */
final class SmsHttp implements PasserelleDeMessage
{
    public function nom(): string
    {
        return 'sms_http';
    }

    public function estConfiguree(): bool
    {
        return filled(config('notifications.sms.url')) && filled(config('notifications.sms.cle_api'));
    }

    public function envoyer(string $destinataire, string $corps): void
    {
        $reponse = Http::withToken((string) config('notifications.sms.cle_api'))
            ->acceptJson()
            ->timeout((int) config('notifications.sms.timeout'))
            ->post((string) config('notifications.sms.url'), [
                (string) config('notifications.sms.champ_destinataire') => $destinataire,
                (string) config('notifications.sms.champ_message') => $corps,
                (string) config('notifications.sms.champ_expediteur') => config('notifications.sms.expediteur'),
            ]);

        if ($reponse->failed()) {
            throw new ErreurMetier('SMS refusé par la passerelle ('.$reponse->status().').', 'sms_refuse', 502);
        }
    }
}
