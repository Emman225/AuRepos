<?php

namespace App\Mail;

use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Recus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecuDeReglementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Le PDF n’est PAS passé au constructeur : ce courriel part en file d’attente, et un binaire ne
     * survit pas à la sérialisation JSON du travail. Il est relu à l’envoi, depuis le fichier conservé.
     */
    public function __construct(public readonly Reglement $reglement) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre reçu '.$this->reglement->numero_recu.' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.recu-de-reglement', with: [
            'prenom' => $this->reglement->tiers->prenoms ?: $this->reglement->tiers->nom,
            'numero' => $this->reglement->numero_recu,
            'montant' => number_format($this->reglement->montant, 0, ',', ' '),
            'lien' => config('plateforme.url_du_site').'/mon-espace',
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => app(Recus::class)->pdf($this->reglement), $this->reglement->numero_recu.'.pdf')->withMime('application/pdf')];
    }
}
