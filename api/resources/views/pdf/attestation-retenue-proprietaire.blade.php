<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Attestation de retenue — {{ $proprietaire->nomAffiche() }} — {{ $releve->periode->format('m/Y') }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1F2A37; }
        h1 { color: #1E3A5F; font-size: 16px; text-align: center; text-transform: uppercase; }
        .discret { color: #5B6676; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .lignes td { padding: 6px 8px; border-bottom: 1px solid #E4DED3; }
        .droite { text-align: right; }
        .bloc { background: #F8F7F4; padding: 10px; margin: 14px 0; }
        .pied { margin-top: 24px; border-top: 1px solid #E4DED3; padding-top: 8px; }
    </style>
</head>
<body>
    <h1>Attestation de retenue à la source</h1>
    <div class="discret" style="text-align:center">{{ $entreprise['nom'] }} @if ($entreprise['ncc']) — NCC {{ $entreprise['ncc'] }} @endif</div>

    <div class="bloc">
        {{ $entreprise['nom'] }} atteste avoir retenu à la source, sur le reversement du mois de
        <b>{{ $releve->periode->translatedFormat('F Y') }}</b> à <b>{{ $proprietaire->nomAffiche() }}</b>, la somme ci-dessous,
        en application du régime fiscal déclaré ({{ $releve->retenue_motif }}).
    </div>

    <table class="lignes">
        <tr><td>Montant brut reversé</td><td class="droite">{{ number_format($releve->montant_brut, 0, ',', ' ') }} {{ $devise }}</td></tr>
        <tr><td>Taux de retenue appliqué</td><td class="droite">{{ $releve->retenue_taux }} %</td></tr>
        <tr><td><b>Montant retenu</b></td><td class="droite"><b>{{ number_format($releve->retenue_montant, 0, ',', ' ') }} {{ $devise }}</b></td></tr>
        <tr><td>Net versé</td><td class="droite">{{ number_format($releve->montant_net, 0, ',', ' ') }} {{ $devise }}</td></tr>
    </table>

    <div class="pied discret">
        Attestation générée le {{ $releve->genere_le->format('d/m/Y') }}, à joindre au dossier fiscal du bénéficiaire.
        Elle ne remplace pas la déclaration et le paiement mensuels à la DGI.
    </div>
</body>
</html>
