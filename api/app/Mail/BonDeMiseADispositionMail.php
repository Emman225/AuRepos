<?php

namespace App\Mail;

use App\Domain\Sejours\Models\Sejour;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Au propriétaire : un séjour est confirmé dans son logement. Ni le client, ni le prix de vente, ni le code. */
class BonDeMiseADispositionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Sejour $sejour) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Séjour confirmé dans votre logement — '.config('app.name'));
    }

    public function content(): Content
    {
        $proprietaire = $this->sejour->logement->residence->proprietaire;

        return new Content(markdown: 'mail.bon-de-mise-a-disposition', with: [
            'prenom' => $proprietaire->utilisateur->prenoms ?: $proprietaire->utilisateur->nom,
            'logement' => $this->sejour->logement->nom.' — '.$this->sejour->logement->residence->nom,
            'arrivee' => $this->sejour->arrivee->format('d/m/Y'),
            'depart' => $this->sejour->depart->format('d/m/Y'),
            'nuitees' => $this->sejour->nombreDeNuits(),
            'lien' => config('plateforme.url_du_site').'/proprietaire',
        ]);
    }
}
