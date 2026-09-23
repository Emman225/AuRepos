<?php

namespace App\Mail;

use App\Domain\Comptes\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Identifiant de connexion généré, envoyé par courriel à la création d'un compte du personnel (CdC § 6.8, § 9.5). */
class BienvenuePersonnelMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $utilisateur) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte '.$this->utilisateur->profil->libelle().' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bienvenue-personnel', with: [
            'prenom' => $this->utilisateur->prenoms ?: $this->utilisateur->nom,
            'profil' => $this->utilisateur->profil->libelle(),
            'identifiant' => $this->utilisateur->identifiant,
            'lien' => config('plateforme.url_du_site').'/mot-de-passe-oublie',
        ]);
    }
}
