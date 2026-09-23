<?php

namespace App\Listeners;

use App\Domain\Caisse\Events\ReglementEffectue;
use App\Domain\Partenaires\Models\DemandePaiementProprietaire;
use App\Mail\BordereauPaiementProprietaireMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Fin du circuit de preuve d'un décaissement : si ce règlement solde une demande de paiement
 * propriétaire, son bordereau part par courriel (CdC § 10). Comme le reçu, cet écouteur
 * n'annule jamais la finalisation ; une panne d'envoi est journalisée, jamais bloquante.
 */
final class EnvoyerLeBordereauProprietaire
{
    public function handle(ReglementEffectue $evenement): void
    {
        $demande = DemandePaiementProprietaire::query()->where('reglement_id', $evenement->reglement->id)->first();
        if ($demande === null) {
            return;
        }

        try {
            Mail::to($evenement->reglement->tiers->email)->send(new BordereauPaiementProprietaireMail($evenement->reglement));
        } catch (Throwable $e) {
            Log::error('Envoi du bordereau propriétaire impossible', ['reglement' => $evenement->reglement->reference, 'erreur' => $e->getMessage()]);
        }
    }
}
