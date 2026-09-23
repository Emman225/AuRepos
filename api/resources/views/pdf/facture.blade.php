<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $facture->type->libelle() }} {{ $facture->numero }}</title>
    <style>
        /* Charte : bleu nuit #1E3A5F, sable #D9C3A5, blanc cassé #F8F7F4. DejaVu : seule police sûre de dompdf pour les accents. */
        @page { margin: 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1F2A37; }
        h1 { color: #1E3A5F; font-size: 17px; margin: 0; }
        .entete { border-bottom: 3px solid #D9C3A5; padding-bottom: 8px; margin-bottom: 10px; }
        .entete td { vertical-align: top; }
        .discret { color: #5B6676; font-size: 9px; }
        .numero { text-align: right; color: #1E3A5F; font-size: 13px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .lignes th { background: #1E3A5F; color: #FFFFFF; text-align: left; padding: 5px 6px; font-size: 9px; }
        .lignes td { padding: 5px 6px; border-bottom: 1px solid #E4DED3; }
        .droite { text-align: right; white-space: nowrap; }
        .colonne-montant { width: 30%; }
        .total td { background: #EFE4D3; color: #1E3A5F; font-weight: bold; font-size: 12px; padding: 7px 6px; }
        .bloc { background: #F8F7F4; padding: 7px 9px; margin: 9px 0; }
        .bloc b { color: #1E3A5F; }
        .pied { margin-top: 14px; border-top: 1px solid #E4DED3; padding-top: 6px; }
        .brouillon { background: #FCEFC7; color: #92620A; padding: 7px 9px; margin: 9px 0; font-weight: bold; }
        .dgi-bloc { background: #E3F0E6; color: #1E5631; margin: 9px 0; }
        .dgi-bloc td { padding: 7px 9px; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            @if ($entreprise['logo_url'])
                <td style="width: 70px;">
                    <img src="{{ $entreprise['logo_url'] }}" alt="" style="max-width: 60px; max-height: 60px;">
                </td>
            @endif
            <td>
                <h1>{{ $entreprise['nom'] }}</h1>
                <div class="discret">
                    @if ($entreprise['siege']) {{ $entreprise['siege'] }}<br> @endif
                    @if ($entreprise['telephone']) Tél. {{ $entreprise['telephone'] }} @endif
                    @if ($entreprise['courriel']) · {{ $entreprise['courriel'] }} @endif
                    @if ($entreprise['ncc'] || $entreprise['rccm']) <br> @endif
                    @if ($entreprise['ncc']) NCC {{ $entreprise['ncc'] }} @endif
                    @if ($entreprise['rccm']) · RCCM {{ $entreprise['rccm'] }} @endif
                    @if ($entreprise['regime']) · {{ $entreprise['regime'] }} @endif
                </div>
            </td>
            <td class="numero">
                {{ mb_strtoupper($facture->type->libelle()) }}<br>{{ $facture->numero }}
                <div class="discret">{{ $facture->created_at?->format('d/m/Y H:i:s') }}</div>
            </td>
        </tr>
    </table>

    @if (! $facture->estTransmise())
        <div class="brouillon">
            {{ $facture->type->libelle() }} non transmise à la DGI : document sans valeur fiscale.
        </div>
    @else
        <table class="dgi-bloc" style="width: 100%;">
            <tr>
                <td style="vertical-align: middle;">
                    Certifiée par la DGI — référence <b>{{ $facture->reference_dgi }}</b>
                </td>
                @if ($qrCodeSvg)
                    <td style="width: 70px; text-align: right;">
                        <img src="data:image/svg+xml;base64,{{ $qrCodeSvg }}" width="60" height="60" alt="QR code">
                    </td>
                @endif
            </tr>
        </table>
    @endif

    <div class="bloc">
        Client : <b>{{ $client['nom'] }}</b>
        @if ($client['telephone']) · {{ $client['telephone'] }} @endif
        @if ($client['email']) · {{ $client['email'] }} @endif
        @if ($client['ncc'] || $client['rccm']) <br> @endif
        @if ($client['ncc']) NCC {{ $client['ncc'] }} @endif
        @if ($client['rccm']) · RCCM {{ $client['rccm'] }} @endif
        @if ($client['bon_de_commande']) <br>Bon de commande : {{ $client['bon_de_commande'] }} @endif
        <br>Séjour {{ $facture->sejour->reference }} — du {{ $facture->sejour->arrivee->format('d/m/Y') }} au {{ $facture->sejour->depart->format('d/m/Y') }}
        @if ($facture->type === \App\Domain\Fiscalite\Enums\TypeDeFacture::Avoir)
            <br>Motif de l'avoir : {{ $facture->motif_avoir }} — annule {{ $facture->factureOrigine->numero }}
        @endif
    </div>

    <table class="lignes">
        <tr><th>Désignation</th><th class="droite">Quantité</th><th class="droite colonne-montant">Montant HT</th></tr>
        @foreach ($facture->lignes as $ligne)
            <tr>
                <td>{{ $ligne['description'] }}</td>
                <td class="droite">{{ $ligne['quantity'] }}</td>
                <td class="droite">{!! str_replace(' ', '&nbsp;', number_format($ligne['amount'], 0, ',', ' ')) !!}&nbsp;{{ $devise }}</td>
            </tr>
        @endforeach
        <tr><td colspan="2">Total HT</td><td class="droite">{!! str_replace(' ', '&nbsp;', number_format($facture->montant_ht, 0, ',', ' ')) !!}&nbsp;{{ $devise }}</td></tr>
        <tr><td colspan="2">TVA</td><td class="droite">{!! str_replace(' ', '&nbsp;', number_format($facture->montant_tva, 0, ',', ' ')) !!}&nbsp;{{ $devise }}</td></tr>
        <tr><td colspan="2">Autres taxes (TDT, taxe de séjour)</td><td class="droite">{!! str_replace(' ', '&nbsp;', number_format($facture->autres_taxes, 0, ',', ' ')) !!}&nbsp;{{ $devise }}</td></tr>
        <tr class="total"><td colspan="2">Net {{ $facture->type === \App\Domain\Fiscalite\Enums\TypeDeFacture::Avoir ? 'à rembourser' : 'à payer' }}</td><td class="droite">{!! str_replace(' ', '&nbsp;', number_format(abs($facture->montant_ttc), 0, ',', ' ')) !!}&nbsp;{{ $devise }}</td></tr>
    </table>

    <div class="pied discret">
        Référence interne : {{ $facture->numero }}
        @if ($facture->reference_dgi) · Référence DGI : {{ $facture->reference_dgi }} @endif
    </div>
</body>
</html>
