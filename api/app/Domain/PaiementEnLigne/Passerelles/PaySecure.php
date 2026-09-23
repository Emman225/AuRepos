<?php

namespace App\Domain\PaiementEnLigne\Passerelles;

use App\Domain\PaiementEnLigne\Contracts\PasserelleDePaiement;
use App\Domain\PaiementEnLigne\Models\PaiementEnLigne;
use App\Support\Api\ErreurMetier;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * PaySecure (mobile money et carte), porté de Mon Gravier (`app/Http/Controllers/PaiementEnLigne.php`).
 *
 * Les clés ne quittent jamais le serveur : le navigateur ne reçoit que l'URL de la passerelle.
 *
 * Les noms de champs ci-dessous suivent l'intégration de Mon Gravier ; ils sont À CONFIRMER
 * avec la documentation du prestataire avant la mise en production, ce que seul un essai avec
 * de vraies clés permettra. Tout est rassemblé ici : une correction ne touche que ce fichier.
 */
final class PaySecure implements PasserelleDePaiement
{
    public function nom(): string
    {
        return 'paysecure';
    }

    public function estConfiguree(): bool
    {
        return filled(config('paiement.paysecure.url')) && filled(config('paiement.paysecure.cle_api'));
    }

    public function initier(PaiementEnLigne $paiement, string $urlDeRetour, string $urlDeRappel): array
    {
        $reponse = $this->requete()->post('/transactions', [
            'merchant_id' => config('paiement.paysecure.marchand'),
            'amount' => $paiement->montant,
            'currency' => 'XOF',
            'reference' => $paiement->reference,
            'description' => 'Séjour '.$paiement->sejour->reference,
            'return_url' => $urlDeRetour,
            'callback_url' => $urlDeRappel,
        ]);

        if ($reponse->failed()) {
            throw new ErreurMetier('La passerelle de paiement est momentanément indisponible. Réessayez ou réglez en agence.', 'passerelle_indisponible', 502);
        }

        $donnees = (array) $reponse->json();
        $url = $donnees['payment_url'] ?? $donnees['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new ErreurMetier('La passerelle de paiement n’a pas renvoyé d’adresse de règlement.', 'passerelle_reponse_invalide', 502);
        }

        return ['url' => $url, 'reference_passerelle' => $donnees['transaction_id'] ?? null, 'reponse' => $donnees];
    }

    public function verifier(PaiementEnLigne $paiement): array
    {
        $reponse = $this->requete()->get('/transactions/'.urlencode($paiement->reference));

        if ($reponse->failed()) {
            // On ne CONCLUT rien d'une passerelle injoignable : le paiement reste en attente, la reprise réessaiera.
            return ['etat' => 'inconnu', 'montant' => null, 'mode' => null, 'reference_passerelle' => null, 'reponse' => ['erreur' => $reponse->status()]];
        }

        $donnees = (array) $reponse->json();

        return [
            'etat' => $this->traduireLEtat((string) ($donnees['status'] ?? '')),
            // Le montant vient de la PASSERELLE, jamais du client : c'est lui qu'on compare au dû.
            'montant' => isset($donnees['amount']) ? (int) $donnees['amount'] : null,
            'mode' => $this->traduireLeMode($donnees['payment_method'] ?? null),
            'reference_passerelle' => $donnees['transaction_id'] ?? null,
            'reponse' => $donnees,
        ];
    }

    public function rappelAuthentique(array $donnees, ?string $signature): bool
    {
        $secret = (string) config('paiement.paysecure.secret_rappel');
        if ($secret === '' || $signature === null) {
            return false;
        }

        $attendue = hash_hmac('sha256', (string) json_encode($donnees, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $secret);

        return hash_equals($attendue, $signature);
    }

    /** @param array<string, mixed> $donnees */
    public function referenceDuRappel(array $donnees): ?string
    {
        $reference = $donnees['reference'] ?? $donnees['merchant_reference'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    private function requete(): PendingRequest
    {
        return Http::baseUrl((string) config('paiement.paysecure.url'))
            ->withToken((string) config('paiement.paysecure.cle_api'))
            ->acceptJson()
            ->timeout((int) config('paiement.paysecure.timeout'))
            // Un réseau qui hoquette ne doit pas faire échouer un paiement.
            ->retry(2, 500, throw: false);
    }

    private function traduireLEtat(string $etat): string
    {
        return match (mb_strtolower($etat)) {
            'success', 'successful', 'completed', 'paid' => 'reussi',
            'failed', 'declined', 'cancelled', 'canceled' => 'echoue',
            'expired', 'timeout' => 'expire',
            'pending', 'processing', 'initiated' => 'en_attente',
            default => 'inconnu',
        };
    }

    private function traduireLeMode(mixed $mode): ?string
    {
        return match (mb_strtolower((string) $mode)) {
            'card', 'visa', 'mastercard' => 'carte',
            'mobile_money', 'orange_money', 'mtn', 'moov', 'wave' => 'mobile_money',
            default => null,
        };
    }
}
