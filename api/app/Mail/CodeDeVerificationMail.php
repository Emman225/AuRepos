<?php

namespace App\Mail;

use App\Domain\Comptes\Enums\UsageDuCode;
use App\Domain\Comptes\Models\User;
use App\Domain\Comptes\Services\CodesDeVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Part en file d'attente : l'inscription répond sans attendre le serveur de
 * messagerie (Mon Gravier envoyait ses courriels dans la requête).
 */
class CodeDeVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $utilisateur,
        public readonly UsageDuCode $usage,
        public readonly string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->usage->objetDuCourriel().' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.code-de-verification', with: [
            'prenom' => $this->utilisateur->prenoms ?: $this->utilisateur->nom,
            'phrase' => $this->usage->phrase(),
            'code' => $this->code,
            'minutes' => CodesDeVerification::DUREE_EN_MINUTES,
        ]);
    }
}
