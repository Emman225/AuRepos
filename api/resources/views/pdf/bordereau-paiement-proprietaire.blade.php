<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bordereau de paiement — {{ $proprietaire->nomAffiche() }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1F2A37; }
        h1 { color: #1E3A5F; font-size: 17px; margin: 0; }
        .entete { border-bottom: 3px solid #D9C3A5; padding-bottom: 8px; margin-bottom: 10px; }
        .discret { color: #5B6676; font-size: 9px; }
        .numero { text-align: right; color: #1E3A5F; font-size: 13px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .lignes td { padding: 5px 6px; border-bottom: 1px solid #E4DED3; }
        .droite { text-align: right; white-space: nowrap; }
        .total td { background: #EFE4D3; color: #1E3A5F; font-weight: bold; font-size: 12px; padding: 7px 6px; }
        .bloc { background: #F8F7F4; padding: 7px 9px; margin: 9px 0; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td><h1>{{ $entreprise['nom'] }}</h1></td>
            <td class="numero">BORDEREAU DE PAIEMENT<br>{{ $reglement->reference }}
                <div class="discret">{{ $reglement->finalise_le?->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="bloc">Versé à <b>{{ $proprietaire->nomAffiche() }}</b></div>

    <table class="lignes">
        <tr><td>Montant brut équivalent</td><td class="droite">{{ number_format($demande->montant_brut_equivalent ?? $demande->montant, 0, ',', ' ') }} {{ $devise }}</td></tr>
        <tr><td>Retenue à la source ({{ $demande->retenue_taux ?? 0 }} %)</td><td class="droite">− {{ number_format($demande->retenue_montant ?? 0, 0, ',', ' ') }} {{ $devise }}</td></tr>
        <tr class="total"><td>Net versé</td><td class="droite">{{ number_format($reglement->montant, 0, ',', ' ') }} {{ $devise }}</td></tr>
    </table>

    <div class="bloc discret">
        Mode de règlement : {{ $reglement->mode->libelle() }} · Référence : {{ $reglement->reference }}
    </div>
</body>
</html>
