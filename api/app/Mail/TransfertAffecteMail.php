<?php

namespace App\Mail;

use App\Domain\Transferts\Models\Transfert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Au client, et à lui seul : son code de prise en charge du transfert (CdC § 6.6). */
class TransfertAffecteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Transfert $transfert, public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre transfert '.$this->transfert->reference.' est organisé — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.transfert-affecte', with: [
            'prenom' => $this->transfert->sejour->client?->prenoms ?: $this->transfert->sejour->client?->nom,
            'reference' => $this->transfert->reference,
            'lieu' => $this->transfert->lieu_de_prise_en_charge,
            'date_heure' => $this->transfert->date_heure_prevue->format('d/m/Y à H:i'),
            'code' => $this->code,
            'lien' => config('plateforme.url_du_site').'/mon-espace',
        ]);
    }
}
