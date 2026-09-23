<?php

namespace App\Mail;

use App\Domain\Partenaires\Models\RelevePropretaire;
use App\Domain\Partenaires\Services\RelevesProprietaires;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Relevé mensuel au propriétaire (P3-PRO-03). Les PDF ne sont PAS passés au constructeur : ce
 * courriel part en file d'attente, et un binaire ne survit pas à la sérialisation JSON du
 * travail (même défaut que App\Mail\RecuDeReglementMail) — ils sont relus depuis le disque à l'envoi.
 */
class RelevePropretaireMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly RelevePropretaire $releve) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre relevé '.$this->releve->periode->translatedFormat('F Y').' — '.config('app.name'));
    }

    public function content(): Content
    {
        $proprietaire = $this->releve->proprietaire()->with('utilisateur')->first();

        return new Content(markdown: 'mail.releve-proprietaire', with: [
            'prenom' => $proprietaire->utilisateur->prenoms ?: $proprietaire->utilisateur->nom,
            'periode' => $this->releve->periode->translatedFormat('F Y'),
            'nuitees' => $this->releve->nuitees_consommees,
            'net' => number_format($this->releve->montant_net, 0, ',', ' '),
            'lien' => config('plateforme.url_du_site').'/proprietaire/releves',
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $service = app(RelevesProprietaires::class);
        $periode = $this->releve->periode->format('m-Y');

        return [
            Attachment::fromData(fn () => $service->pdf($this->releve), "releve-{$periode}.pdf")->withMime('application/pdf'),
            Attachment::fromData(fn () => $service->attestationPdf($this->releve), "attestation-retenue-{$periode}.pdf")->withMime('application/pdf'),
        ];
    }
}
