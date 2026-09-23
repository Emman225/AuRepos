<?php

namespace App\Domain\Fiscalite\Services;

use App\Domain\Caisse\Services\Numerotation;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Enums\StatutDeTransmissionFne;
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\AlerteStickersFneMail;
use App\Support\Api\ErreurMetier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

/**
 * Une facture par séjour (CdC § 9.4) : proforma tant que rien n'est certifié, facture ou avoir
 * une fois transmis à la DGI. Le devis FIGÉ sur le séjour (`Sejour::devis`, lui-même déjà conçu
 * pour la FNE — voir `DevisDeSejour::autresTaxes()`) est la SEULE source des montants : rien n'est
 * recalculé ici.
 */
final class Factures
{
    public function __construct(
        private readonly Numerotation $numerotation,
        private readonly TransmissionFne $transmission,
        private readonly Parametres $parametres,
    ) {}

    public function genererPourUnSejour(Sejour $sejour, TypeDeFacture $type, User $auteur): Facture
    {
        if ($type === TypeDeFacture::Avoir) {
            throw new ErreurMetier('Un avoir se crée depuis la facture qu’il annule, pas depuis un séjour.', 'avoir_sans_origine', 422);
        }

        if ($type === TypeDeFacture::Facture) {
            $dejaEmise = Facture::query()->where('sejour_id', $sejour->id)->where('type', TypeDeFacture::Facture->value)->exists();
            if ($dejaEmise) {
                throw new ErreurMetier('Ce séjour a déjà une facture : émettez un avoir pour la corriger.', 'facture_deja_emise', 422);
            }
        }

        $devis = $sejour->devis ?? [];
        if ($devis === []) {
            throw new ErreurMetier('Ce séjour n’a pas de devis figé à facturer.', 'devis_absent', 422);
        }

        $client = Client::de($sejour->client);
        $lignes = $this->construireLesLignes($devis, $client);
        $numero = sprintf('%s-%d-%03d', $type === TypeDeFacture::Proforma ? 'PRO' : 'FAC', now()->year, $this->numerotation->suivant('factures', now()->year));

        return DB::transaction(fn (): Facture => Facture::create([
            'numero' => $numero,
            'type' => $type->value,
            'sejour_id' => $sejour->id,
            'client_id' => $sejour->client_id,
            'montant_ht' => (int) Arr::get($devis, 'total_ht', 0),
            'montant_tva' => (int) Arr::get($devis, 'total_tva', 0),
            'autres_taxes' => (int) Arr::get($devis, 'autres_taxes', 0),
            'montant_ttc' => (int) Arr::get($devis, 'net_a_payer', 0),
            'lignes' => $lignes,
            'statut_transmission' => StatutDeTransmissionFne::ATransmettre->value,
            'genere_par' => $auteur->id,
        ]));
    }

    public function transmettre(Facture $facture, User $administrateur): Facture
    {
        if ($facture->estTransmise()) {
            throw new ErreurMetier('Cette facture est déjà transmise.', 'facture_deja_transmise', 409);
        }

        $payload = $this->payloadDeCertification($facture);

        try {
            $reponse = $this->transmission->signer($payload);
        } catch (ErreurMetier $e) {
            $facture->update([
                'statut_transmission' => $e->codeMetier === 'fne_non_configuree' ? StatutDeTransmissionFne::NonConfiguree->value : StatutDeTransmissionFne::Refusee->value,
                'motif_refus_dgi' => $e->getMessage(),
                'payload_fne' => $payload,
            ]);
            throw $e;
        }

        $facture->update([
            'statut_transmission' => StatutDeTransmissionFne::Transmise->value,
            'reference_dgi' => $reponse['reference'],
            'token_qr' => $reponse['token'],
            'ncc_dgi' => $reponse['ncc'],
            'solde_stickers' => $reponse['balance_sticker'],
            'motif_refus_dgi' => null,
            'payload_fne' => $payload,
            'reponse_fne' => $reponse,
            'transmise_par' => $administrateur->id,
            'transmise_le' => now(),
        ]);

        $this->alerterSiStickersBas((int) $reponse['balance_sticker']);

        return $facture->refresh();
    }

    /**
     * Sous le seuil, chaque transmission relance l'alerte (CdC § 9.4) : mieux vaut prévenir
     * plusieurs fois qu'une seule, tant que le solde reste critique. Un échec d'envoi ne doit
     * jamais faire échouer la transmission qui vient de réussir.
     */
    private function alerterSiStickersBas(int $solde): void
    {
        $seuil = (int) config('fne.seuil_alerte_stickers');
        if ($solde > $seuil) {
            return;
        }

        $destinataires = User::query()->whereIn('profil', [Profil::Administrateur->value, Profil::SuperAdministrateur->value])->pluck('email');

        try {
            foreach ($destinataires as $email) {
                Mail::to($email)->send(new AlerteStickersFneMail($solde, $seuil));
            }
        } catch (Throwable $e) {
            Log::warning('Alerte stickers FNE non envoyée', ['solde' => $solde, 'erreur' => $e->getMessage()]);
        }
    }

    public function emettreUnAvoir(Facture $factureOrigine, string $motif, User $administrateur): Facture
    {
        if (! $factureOrigine->estTransmise()) {
            throw new ErreurMetier('Seule une facture transmise à la DGI peut recevoir un avoir.', 'facture_non_transmise', 422);
        }
        if ($factureOrigine->type === TypeDeFacture::Avoir) {
            throw new ErreurMetier('Un avoir ne peut pas lui-même être annulé par un avoir.', 'avoir_sur_avoir', 422);
        }

        $lignesDgi = $this->lignesDgiDOrigine($factureOrigine);
        // /refund attend l'identifiant INTERNE de la facture DGI (`invoice.id`, un UUID), pas sa
        // référence lisible (`reference_dgi`) : vérifié par un essai réel contre l'environnement
        // de test de la DGI, qui rejette la référence avec « invalid input syntax for type uuid ».
        $idDgi = (string) ($factureOrigine->reponse_fne['invoice']['id'] ?? '');
        $reponse = $this->transmission->rembourser($idDgi, $lignesDgi);

        $numero = sprintf('AVR-%d-%03d', now()->year, $this->numerotation->suivant('avoirs', now()->year));

        return DB::transaction(fn (): Facture => Facture::create([
            'numero' => $numero,
            'type' => TypeDeFacture::Avoir->value,
            'sejour_id' => $factureOrigine->sejour_id,
            'client_id' => $factureOrigine->client_id,
            'facture_origine_id' => $factureOrigine->id,
            'motif_avoir' => $motif,
            'montant_ht' => -$factureOrigine->montant_ht,
            'montant_tva' => -$factureOrigine->montant_tva,
            'autres_taxes' => -$factureOrigine->autres_taxes,
            'montant_ttc' => -$factureOrigine->montant_ttc,
            'lignes' => $factureOrigine->lignes,
            'statut_transmission' => StatutDeTransmissionFne::Transmise->value,
            'reference_dgi' => $reponse['reference'],
            'token_qr' => $reponse['token'],
            'ncc_dgi' => $reponse['ncc'],
            'solde_stickers' => $reponse['balance_sticker'],
            'reponse_fne' => $reponse,
            'transmise_par' => $administrateur->id,
            'transmise_le' => now(),
            'genere_par' => $administrateur->id,
        ]));
    }

    /**
     * @param  array<string, mixed>  $devis
     * @return list<array<string, mixed>>
     */
    private function construireLesLignes(array $devis, Client $client): array
    {
        $lignes = [];

        $hebergementNetHt = (int) Arr::get($devis, 'hebergement_net_ht', 0);
        $nombreDeNuits = max(1, (int) Arr::get($devis, 'nombre_de_nuits', 1));
        $extrasHt = (int) Arr::get($devis, 'extras_ht', 0);
        $transfertHt = (int) Arr::get($devis, 'transfert_ht', 0);
        $tdt = (int) Arr::get($devis, 'tdt', 0);
        $taxeDeSejour = (int) Arr::get($devis, 'taxe_de_sejour', 0);

        // Un client exonéré (TVAD/TVAC) porte SON code sur la ligne — jamais une absence de taxe :
        // c'est ce code qui déclenche la mention « TVA NON FACTURÉE » sur la facture (CdC § 9.4).
        $taxesHebergement = $this->codesDeTaxe($client->tva_hebergement, $client->code_exoneration);
        $taxesTransfert = $this->codesDeTaxe($client->tva_transfert, $client->code_exoneration);

        if ($hebergementNetHt > 0) {
            $lignes[] = ['description' => 'Hébergement', 'quantity' => (float) $nombreDeNuits, 'amount' => (int) round($hebergementNetHt / $nombreDeNuits), 'taxes' => $taxesHebergement];
        }
        if ($extrasHt > 0) {
            $lignes[] = ['description' => 'Extras', 'quantity' => 1, 'amount' => $extrasHt, 'taxes' => $taxesHebergement];
        }
        if ($transfertHt > 0) {
            $lignes[] = ['description' => 'Transfert', 'quantity' => 1, 'amount' => $transfertHt, 'taxes' => $taxesTransfert];
        }
        // La DGI rejette (400) toute ligne avec `taxes: []` — vérifié par un essai réel contre son
        // environnement de test. Le TDT et la taxe de séjour sont déjà eux-mêmes des taxes, sans
        // TVA en plus dessus : `TVAD` (0 %, exonération légale) est le seul code du schéma qui
        // laisse le montant de la ligne intact tout en satisfaisant « une taxe non vide ».
        // `customTaxes` a été essayé d'abord et écarté : son `amount` s'est avéré être un TAUX
        // appliqué à la base, pas un montant fixe (un essai réel l'a démultiplié de plus de 20×).
        if ($tdt > 0) {
            $lignes[] = ['description' => 'Taxe de développement touristique (TDT)', 'quantity' => 1, 'amount' => $tdt, 'taxes' => ['TVAD']];
        }
        if ($taxeDeSejour > 0) {
            $lignes[] = ['description' => 'Taxe de séjour', 'quantity' => 1, 'amount' => $taxeDeSejour, 'taxes' => ['TVAD']];
        }

        return $lignes;
    }

    /** @return list<string> */
    private function codesDeTaxe(bool $assujetti, ?string $codeExoneration): array
    {
        if ($codeExoneration !== null) {
            return [$codeExoneration];
        }

        return $assujetti ? [(string) config('fne.default_tax')] : [];
    }

    /** @return array<string, mixed> */
    private function payloadDeCertification(Facture $facture): array
    {
        $client = Client::de($facture->client);
        $sejour = $facture->sejour;

        $payload = [
            'invoiceType' => 'sale',
            'paymentMethod' => match ($sejour->mode_reglement) {
                'a_terme' => 'deferred',
                'en_ligne' => 'card',
                default => (string) config('fne.default_payment_method'),
            },
            'template' => match ($client->nature) {
                'b2b' => 'B2B', 'b2g' => 'B2G', 'b2f' => 'B2F',
                default => (string) config('fne.default_template'),
            },
            'pointOfSale' => (string) config('fne.point_of_sale'),
            'establishment' => (string) config('fne.establishment'),
            'clientCompanyName' => $client->nature === 'b2c' ? $facture->client->nomComplet() : (string) ($client->raison_sociale ?: $facture->client->nomComplet()),
            'clientPhone' => (string) ($facture->client->telephone ?? ''),
            'clientEmail' => (string) $facture->client->email,
            'isRne' => false,
            'foreignCurrency' => '',
            'foreignCurrencyRate' => 0,
            'items' => array_map(static fn (array $ligne): array => [
                'description' => $ligne['description'],
                'quantity' => $ligne['quantity'],
                'amount' => $ligne['amount'],
                'taxes' => $ligne['taxes'],
            ], $facture->lignes),
        ];

        if (in_array($client->nature, ['b2b', 'b2g', 'b2f'], true) && filled($client->ncc)) {
            $payload['clientNcc'] = $client->ncc;
        }

        return $payload;
    }

    /**
     * Reconstitue, du mieux que permet la réponse DGI conservée à la certification, les
     * identifiants de lignes attendus par `/refund` — à ajuster une fois la forme exacte de
     * `invoice.items` confirmée par un essai réel (même remarque que `PaySecure`).
     *
     * @return list<array{id: string, quantity: float}>
     */
    private function lignesDgiDOrigine(Facture $factureOrigine): array
    {
        $items = (array) ($factureOrigine->reponse_fne['invoice']['items'] ?? []);
        if ($items === []) {
            throw new ErreurMetier('La réponse DGI de la facture d’origine ne contient pas le détail des lignes : avoir impossible.', 'fne_lignes_introuvables', 422);
        }

        return array_map(static fn (array $item): array => [
            'id' => (string) ($item['id'] ?? ''),
            'quantity' => (float) ($item['quantity'] ?? 1),
        ], $items);
    }

    /** Le PDF tel qu'émis (une fois transmis) ; régénéré à l'identique s'il manque sur le disque. */
    public function pdf(Facture $facture): string
    {
        $chemin = 'factures/'.$facture->created_at?->format('Y').'/'.$facture->numero.'.pdf';
        if (Storage::disk('local')->exists($chemin)) {
            return (string) Storage::disk('local')->get($chemin);
        }

        $facture->loadMissing(['sejour', 'client', 'factureOrigine']);
        $contenu = Pdf::loadView('pdf.facture', $this->donneesPdf($facture))->setPaper('a4')->output();
        Storage::disk('local')->put($chemin, $contenu);

        return $contenu;
    }

    /**
     * Publique : les tests vérifient ici le contenu réel transmis au gabarit, le PDF compilé étant compressé (illisible en texte).
     *
     * @return array<string, mixed>
     */
    public function donneesPdf(Facture $facture): array
    {
        $p = fn (string $cle) => $this->parametres->valeur($cle);
        $client = Client::de($facture->client);

        return [
            'facture' => $facture,
            'entreprise' => [
                'nom' => $p('entreprise.raison_sociale') ?: $p('general.nom_plateforme'), 'siege' => $p('entreprise.siege'),
                'telephone' => $p('entreprise.telephone') ?: $p('general.telephone'), 'courriel' => $p('entreprise.courriel') ?: $p('general.courriel'),
                'ncc' => $p('entreprise.ncc'), 'rccm' => $p('entreprise.rccm'), 'regime' => $p('entreprise.regime_imposition'),
                'logo_url' => $p('entreprise.logo_url'),
            ],
            'client' => [
                'nom' => $client->nature === 'b2c' ? $facture->client->nomComplet() : (string) ($client->raison_sociale ?: $facture->client->nomComplet()),
                'telephone' => $facture->client->telephone, 'email' => $facture->client->email,
                'ncc' => $client->ncc, 'rccm' => $client->rccm,
                // Bon de commande interne : exigé de toute organisation à la réservation (CdC § 5.2, gabarit B2G notamment).
                'bon_de_commande' => $facture->sejour->bon_de_commande,
            ],
            'devise' => $p('general.devise'),
            'qrCodeSvg' => $facture->token_qr ? base64_encode((string) QrCode::size(110)->margin(0)->generate($facture->token_qr)) : null,
        ];
    }
}
