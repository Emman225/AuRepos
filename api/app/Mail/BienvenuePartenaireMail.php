<?php

namespace App\Mail;

use App\Domain\Comptes\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BienvenuePartenaireMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $utilisateur) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte '.$this->utilisateur->profil->libelle().' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bienvenue-partenaire', with: [
            'prenom' => $this->utilisateur->prenoms ?: $this->utilisateur->nom,
            'profil' => $this->utilisateur->profil->libelle(),
            'email' => $this->utilisateur->email,
            'lien' => config('plateforme.url_du_site').'/mot-de-passe-oublie',
        ]);
    }
}
