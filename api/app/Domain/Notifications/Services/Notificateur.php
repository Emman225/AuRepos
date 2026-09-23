<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\PasserelleDeMessage;
use App\Domain\Notifications\Enums\CanalNotification;
use App\Domain\Notifications\Enums\EtatNotification;
use App\Domain\Notifications\Enums\ModeleDeMessage;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Passerelles\PasserelleEssai;
use App\Domain\Notifications\Passerelles\SmsHttp;
use App\Domain\Notifications\Passerelles\WhatsAppCloudApi;
use App\Domain\Parametres\Services\Parametres;
use App\Jobs\EnvoyerNotification as EnvoyerNotificationJob;
use App\Mail\NotificationGeneriqueMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifications multicanal (CdC § 6.7 et § 13.2) : composées depuis un modèle paramétrable,
 * journalisées avant tout envoi, mises en file d'attente, et relançables sur échec — qui
 * n'interrompt JAMAIS l'opération qui a demandé l'envoi.
 */
final class Notificateur
{
    public function __construct(private readonly Parametres $parametres) {}

    /**
     * Compose et met en file d'attente une notification. Le rendu (sujet, corps) est figé
     * ICI : une relance renverra exactement ce texte, même si le modèle change entre-temps.
     *
     * @param  array<string, string|int|null>  $donnees
     */
    public function preparer(ModeleDeMessage $modele, CanalNotification $canal, string $destinataire, array $donnees): Notification
    {
        $cle = $modele->cleParametre();
        $sujet = $canal === CanalNotification::Email
            ? RenduDeModele::rendre((string) $this->parametres->valeur($cle.'_sujet'), $donnees)
            : null;
        $corps = RenduDeModele::rendre((string) $this->parametres->valeur($cle.'_corps'), $donnees);

        $notification = Notification::create([
            'canal' => $canal, 'modele' => $modele, 'destinataire' => $destinataire,
            'sujet' => $sujet, 'corps' => $corps, 'donnees' => $donnees, 'etat' => EtatNotification::EnAttente,
        ]);

        EnvoyerNotificationJob::dispatch($notification->id);

        return $notification;
    }

    /**
     * Envoi effectif, appelé par le job (et par la reprise planifiée). Ne relance JAMAIS
     * d'exception : l'échec est journalisé sur la ligne elle-même.
     */
    public function envoyer(Notification $notification): void
    {
        if ($notification->etat === EtatNotification::Envoyee) {
            return; // déjà parti : une reprise n'envoie pas deux fois
        }

        try {
            match ($notification->canal) {
                // sendNow(), pas send() : ce code s'exécute déjà DANS un job. « send() » sur une
                // Mailable ShouldQueue la remettrait en file au lieu de l'expédier, et l'état
                // « envoyée » ne serait plus vérifié par rien.
                CanalNotification::Email => Mail::to($notification->destinataire)->sendNow(
                    new NotificationGeneriqueMail((string) $notification->sujet, $notification->corps),
                ),
                CanalNotification::Sms, CanalNotification::Whatsapp => $this->passerellePour($notification->canal)
                    ->envoyer($notification->destinataire, $notification->corps),
            };

            $notification->update(['etat' => EtatNotification::Envoyee, 'envoyee_le' => now(), 'erreur' => null, 'tentatives' => $notification->tentatives + 1]);
        } catch (Throwable $e) {
            $notification->update(['etat' => EtatNotification::Echouee, 'erreur' => mb_substr($e->getMessage(), 0, 255), 'tentatives' => $notification->tentatives + 1]);
            Log::warning('Envoi de notification échoué', ['id' => $notification->id, 'canal' => $notification->canal->value, 'erreur' => $e->getMessage()]);
        }
    }

    /** Reprise planifiée : notifications en attente ou échouées, sous le nombre maximal de tentatives. */
    public function reprendre(): int
    {
        $limite = now()->subMinutes((int) config('notifications.reprise_minutes'));
        $maxTentatives = (int) config('notifications.tentatives_max');
        $repris = 0;

        Notification::query()
            ->whereIn('etat', [EtatNotification::EnAttente, EtatNotification::Echouee])
            ->where('tentatives', '<', $maxTentatives)
            ->where('created_at', '<=', $limite)
            ->orderBy('created_at')
            ->limit(200)
            ->each(function (Notification $notification) use (&$repris): void {
                $this->envoyer($notification);
                $repris++;
            });

        return $repris;
    }

    private function passerellePour(CanalNotification $canal): PasserelleDeMessage
    {
        return match ($canal) {
            CanalNotification::Sms => match ((string) config('notifications.sms_passerelle')) {
                'sms_http' => app(SmsHttp::class),
                default => new PasserelleEssai('sms'),
            },
            CanalNotification::Whatsapp => match ((string) config('notifications.whatsapp_passerelle')) {
                'whatsapp_cloud_api' => app(WhatsAppCloudApi::class),
                default => new PasserelleEssai('whatsapp'),
            },
            CanalNotification::Email => throw new \LogicException('Le courriel ne passe pas par une passerelle texte.'),
        };
    }
}
