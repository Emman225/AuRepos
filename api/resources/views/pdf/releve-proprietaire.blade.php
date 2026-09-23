<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Relevé {{ $releve->periode->format('m/Y') }} — {{ $proprietaire->nomAffiche() }}</title>
    <style>
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
        .total td { background: #EFE4D3; color: #1E3A5F; font-weight: bold; font-size: 12px; padding: 7px 6px; }
        .bloc { background: #F8F7F4; padding: 7px 9px; margin: 9px 0; }
        .bloc b { color: #1E3A5F; }
        .pied { margin-top: 14px; border-top: 1px solid #E4DED3; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td>
                <h1>{{ $entreprise['nom'] }}</h1>
                <div class="discret">
                    @if ($entreprise['siege']) {{ $entreprise['siege'] }}<br> @endif
                    @if ($entreprise['telephone']) Tél. {{ $entreprise['telephone'] }} @endif
                    @if ($entreprise['courriel']) · {{ $entreprise['courriel'] }} @endif
                </div>
            </td>
            <td class="numero">
                RELEVÉ PROPRIÉTAIRE<br>{{ $releve->periode->translatedFormat('F Y') }}
                <div class="discret">Généré le {{ $releve->genere_le->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="bloc">
        Propriétaire : <b>{{ $proprietaire->nomAffiche() }}</b>
        @if ($proprietaire->interne) — compte interne, reversement sans retenue @endif
    </div>

    <table class="lignes">
        <tr><th>Détail</th><th class="droite">Montant</th></tr>
        <tr><td>Nuitées consommées</td><td class="droite">{{ $releve->nuitees_consommees }}</td></tr>
        <tr><td>Montant brut ({{ $modeRemuneration }})</td><td class="droite">{{ number_format($releve->montant_brut, 0, ',', ' ') }} {{ $devise }}</td></tr>
        <tr><td>Charges refacturées</td><td class="droite">− {{ number_format($releve->charges_refacturees, 0, ',', ' ') }} {{ $devise }}</td></tr>
        <tr><td>Part des cautions retenues</td><td class="droite">+ {{ number_format($releve->part_cautions, 0, ',', ' ') }} {{ $devise }}</td></tr>
        @if ($proprietaire->assujetti_tva)
            <tr><td>TVA</td><td class="droite">+ {{ number_format($releve->tva, 0, ',', ' ') }} {{ $devise }}</td></tr>
        @endif
        <tr><td>Retenue à la source ({{ $releve->retenue_taux }} % — {{ $releve->retenue_motif }})</td><td class="droite">− {{ number_format($releve->retenue_montant, 0, ',', ' ') }} {{ $devise }}</td></tr>
        <tr class="total"><td>Net à reverser</td><td class="droite">{{ number_format($releve->montant_net, 0, ',', ' ') }} {{ $devise }}</td></tr>
    </table>

    @if ($charges->isNotEmpty())
        <div class="bloc"><b>Détail des charges refacturées</b></div>
        <table class="lignes">
            <tr><th>Nature</th><th>Motif</th><th class="droite">Montant</th></tr>
            @foreach ($charges as $charge)
                <tr><td>{{ \App\Domain\Partenaires\Models\ChargeProprietaire::NATURES[$charge->nature] ?? $charge->nature }}</td><td>{{ $charge->motif }}</td><td class="droite">{{ number_format($charge->montant, 0, ',', ' ') }} {{ $devise }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="pied discret">
        Relevé établi sur les nuitées réellement consommées du mois, jamais sur les nuitées réservées.
        Il ne vaut pas facture.
    </div>
</body>
</html>
