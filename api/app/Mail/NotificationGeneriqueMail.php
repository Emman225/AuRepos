<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Courriel d'un modèle de message paramétrable (CdC § 6.7) : le sujet et le corps sont déjà
 * composés par `Notificateur`, avec l'habillage graphique commun aux courriels de la plateforme.
 */
class NotificationGeneriqueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $sujet, public readonly string $corps) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->sujet);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.notification-generique', with: ['corps' => $this->corps]);
    }
}
