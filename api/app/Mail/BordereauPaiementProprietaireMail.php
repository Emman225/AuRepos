<?php

namespace App\Mail;

use App\Domain\Caisse\Models\Reglement;
use App\Domain\Partenaires\Services\DemandesPaiementProprietaire;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Bordereau de paiement au propriétaire, à la finalisation du décaissement (CdC § 10 : « reçoivent leur bordereau par courriel »). */
class BordereauPaiementProprietaireMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Reglement $reglement) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre paiement '.$this->reglement->reference.' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bordereau-paiement-proprietaire', with: [
            'prenom' => $this->reglement->tiers->prenoms ?: $this->reglement->tiers->nom,
            'net' => number_format($this->reglement->montant, 0, ',', ' '),
            'lien' => config('plateforme.url_du_site').'/proprietaire/paiements',
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(DemandesPaiementProprietaire::class)->bordereauPdf($this->reglement), $this->reglement->reference.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
