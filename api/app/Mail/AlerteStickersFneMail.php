<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Alerte administrateurs : le solde de stickers FNE de la DGI passe sous le seuil d'alerte (CdC § 9.4). */
class AlerteStickersFneMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $solde,
        public readonly int $seuil,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Stickers FNE bas : '.$this->solde.' restants — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.alerte-stickers-fne', with: [
            'solde' => $this->solde,
            'seuil' => $this->seuil,
        ]);
    }
}
