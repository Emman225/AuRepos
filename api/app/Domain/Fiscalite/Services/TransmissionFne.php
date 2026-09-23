<?php

namespace App\Domain\Fiscalite\Services;

use App\Support\Api\ErreurMetier;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Appel réel du webservice FNE de la DGI (CdC § 9.4, 12). Contrat vérifié sur le SDK officieux
 * PRODESTIC/fne-sdk-php (code source lu, pas deviné) : POST {base}/external/invoices/sign pour
 * certifier, POST {base}/external/invoices/{reference}/refund pour un avoir, authentification par
 * `Authorization: Bearer <clé>`. Rien ici n'appelle jamais le réseau en environnement de test :
 * `Http::fake()` intercepte cette façade dans toute la suite Pest.
 */
final class TransmissionFne
{
    public function estConfiguree(): bool
    {
        return (bool) config('fne.enabled') && filled(config('fne.base_url')) && filled(config('fne.api_key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ncc: string, reference: string, token: string, warning: bool, balance_sticker: int, invoice: array<string, mixed>}
     */
    public function signer(array $payload): array
    {
        if (! $this->estConfiguree()) {
            throw new ErreurMetier('La transmission FNE n’est pas configurée : identifiants DGI absents.', 'fne_non_configuree', 422);
        }

        $reponse = $this->requete()->post('/external/invoices/sign', $payload);

        return $this->traiter($reponse, 'certification');
    }

    /**
     * Avoir total : la DGI attend la liste des lignes de la facture d'origine à annuler,
     * identifiées par les `id` qu'ELLE a attribués à la certification (jamais les nôtres).
     *
     * @param  array<int, array{id: string, quantity: float}>  $lignes
     * @return array{ncc: string, reference: string, token: string, warning: bool, balance_sticker: int, invoice: array<string, mixed>}
     */
    public function rembourser(string $referenceOrigine, array $lignes): array
    {
        if (! $this->estConfiguree()) {
            throw new ErreurMetier('La transmission FNE n’est pas configurée : identifiants DGI absents.', 'fne_non_configuree', 422);
        }

        $reponse = $this->requete()->post("/external/invoices/{$referenceOrigine}/refund", ['items' => $lignes]);

        return $this->traiter($reponse, 'avoir');
    }

    /** @return array{ncc: string, reference: string, token: string, warning: bool, balance_sticker: int, invoice: array<string, mixed>} */
    private function traiter(Response $reponse, string $operation): array
    {
        if ($reponse->successful()) {
            $donnees = (array) $reponse->json();

            return [
                'ncc' => (string) ($donnees['ncc'] ?? ''),
                'reference' => (string) ($donnees['reference'] ?? ''),
                'token' => (string) ($donnees['token'] ?? ''),
                'warning' => (bool) ($donnees['warning'] ?? false),
                'balance_sticker' => (int) ($donnees['balance_sticker'] ?? 0),
                'invoice' => (array) ($donnees['invoice'] ?? []),
            ];
        }

        $donnees = (array) $reponse->json();
        $motif = is_string($donnees['message'] ?? null) ? $donnees['message'] : "La DGI a refusé la {$operation} sans motif exploitable.";

        throw new ErreurMetier($motif, 'fne_refusee', 422);
    }

    private function requete(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('fne.base_url'), '/'))
            ->withToken((string) config('fne.api_key'))
            ->acceptJson()
            ->timeout((int) config('fne.timeout'));
    }
}
