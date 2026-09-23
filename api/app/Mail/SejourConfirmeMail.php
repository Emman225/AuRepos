<?php

namespace App\Mail;

use App\Domain\Sejours\Models\Sejour;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Au client, et à lui seul : son code d'arrivée, l'adresse exacte et les consignes d'accès (CdC § 6.1). */
class SejourConfirmeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Sejour $sejour, public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Séjour '.$this->sejour->reference.' confirmé — '.config('app.name'));
    }

    public function content(): Content
    {
        $residence = $this->sejour->logement->residence;

        return new Content(markdown: 'mail.sejour-confirme', with: [
            'prenom' => $this->sejour->client?->prenoms ?: $this->sejour->client?->nom,
            'reference' => $this->sejour->reference,
            'logement' => $this->sejour->logement->nom.' — '.$residence->nom,
            'arrivee' => $this->sejour->arrivee->format('d/m/Y'),
            'depart' => $this->sejour->depart->format('d/m/Y'),
            'code' => $this->code,
            'adresse' => $residence->adresse,
            'repere' => $residence->repere,
            'consignes' => $residence->consignes_acces,
            'lien' => config('plateforme.url_du_site').'/mon-espace',
        ]);
    }
}
