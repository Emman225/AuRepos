<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $reglement->numero_recu }}</title>
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
        .colonne-montant { width: 34%; }
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
                    @if ($entreprise['ncc'] || $entreprise['rccm']) <br> @endif
                    @if ($entreprise['ncc']) NCC {{ $entreprise['ncc'] }} @endif
                    @if ($entreprise['rccm']) · RCCM {{ $entreprise['rccm'] }} @endif
                    @if ($entreprise['regime']) · {{ $entreprise['regime'] }} @endif
                </div>
            </td>
            <td class="numero">
                REÇU<br>{{ $reglement->numero_recu }}
                <div class="discret">{{ $reglement->finalise_le?->format('d/m/Y H:i:s') }}</div>
            </td>
        </tr>
    </table>

    <div class="bloc">
        Reçu de <b>{{ $reglement->tiers->nomComplet() }}</b>
        @if ($reglement->tiers->telephone) · {{ $reglement->tiers->telephone }} @endif
        <br>la somme de <b>{{ number_format($reglement->montant, 0, ',', ' ') }} {{ $devise }}</b>
        ({{ $enLettres }} francs CFA)
    </div>

    <table class="lignes">
        <tr><th>Objet</th><th class="droite colonne-montant">Montant</th></tr>
        @foreach ($lignes as $ligne)
            <tr><td>{{ $ligne['libelle'] }}</td><td class="droite">{!! str_replace(' ', '&nbsp;', number_format($ligne['montant'], 0, ',', ' ')) !!}&nbsp;{{ $devise }}</td></tr>
        @endforeach
        @if ($enAvance > 0)
            <tr><td>Avance conservée sur votre compte — elle se déduira de vos prochains séjours</td><td class="droite">{!! str_replace(' ', '&nbsp;', number_format($enAvance, 0, ',', ' ')) !!}&nbsp;{{ $devise }}</td></tr>
        @endif
        <tr class="total"><td>Total reçu</td><td class="droite">{!! str_replace(' ', '&nbsp;', number_format($reglement->montant, 0, ',', ' ')) !!}&nbsp;{{ $devise }}</td></tr>
    </table>

    <div class="bloc">
        Mode de règlement : <b>{{ $reglement->mode->libelle() }}</b>
        @if ($reglement->reference_du_mode) · référence {{ $reglement->reference_du_mode }} @endif
        <br>Guichet : {{ $reglement->guichet->libelle() }} · {{ $reglement->agence->nom }}
        · saisi par {{ $reglement->auteur->nomComplet() }}
        <br>Référence interne : {{ $reglement->reference }}
    </div>

    <div class="pied discret">
        Ce reçu atteste un règlement validé, prouvé et finalisé par trois personnes distinctes.
        Il ne vaut pas facture : la facture normalisée est émise à part.
    </div>
</body>
</html>
