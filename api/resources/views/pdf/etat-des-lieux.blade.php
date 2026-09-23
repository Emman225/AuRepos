<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>État des lieux {{ $sejour->reference }}</title>
    <style>
        /* Charte : bleu nuit #1E3A5F, sable #D9C3A5, blanc cassé #F8F7F4. Une page (P2-SEJ-02). */
        @page { margin: 10mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #1F2A37; }
        h1 { color: #1E3A5F; font-size: 15px; margin: 0; }
        .entete { border-bottom: 3px solid #D9C3A5; padding-bottom: 6px; margin-bottom: 8px; }
        .entete td { vertical-align: top; }
        .discret { color: #5B6676; font-size: 8px; }
        .droite { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .lignes th { background: #1E3A5F; color: #FFFFFF; text-align: left; padding: 4px 5px; font-size: 8px; }
        .lignes td { padding: 4px 5px; border-bottom: 1px solid #E4DED3; font-size: 8.5px; }
        .ecart { background: #FBEAEA; }
        .bloc { background: #F8F7F4; padding: 6px 8px; margin: 7px 0; }
        .bloc b { color: #1E3A5F; }
        .signatures td { width: 50%; padding-top: 10px; vertical-align: top; }
        .signature-img { max-height: 60px; max-width: 200px; border: 1px solid #E4DED3; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td>
                <h1>État des lieux — {{ $sejour->reference }}</h1>
                <div class="discret">
                    {{ $sejour->logement->nom }} · {{ $sejour->logement->residence->nom }}<br>
                    Séjour du {{ $sejour->arrivee->format('d/m/Y') }} au {{ $sejour->depart->format('d/m/Y') }}
                    @if ($sejour->client) · {{ $sejour->client->nomComplet() }} @endif
                </div>
            </td>
            <td class="droite">
                {!! $codeBarres !!}
                <div class="discret">{{ $sejour->reference }}</div>
            </td>
        </tr>
    </table>

    <table class="lignes">
        <tr>
            <th>Élément</th>
            <th>Constat à l’entrée</th>
            <th>Constat à la sortie</th>
        </tr>
        @forelse ($comparaison as $ligne)
            <tr @class(['ecart' => $ligne['ecart']])>
                <td>{{ $ligne['libelle'] }}</td>
                <td>{{ $ligne['entree'] ?? '—' }}</td>
                <td>{{ $ligne['sortie'] ?? '—' }}</td>
            </tr>
        @empty
            @foreach (($entree->lignes ?? []) as $ligne)
                <tr><td>{{ $ligne->libelle }}</td><td>{{ $ligne->observation ?? '—' }}</td><td>—</td></tr>
            @endforeach
        @endforelse
    </table>

    @if ($entree?->commentaire_general || $sortie?->commentaire_general)
        <div class="bloc">
            @if ($entree?->commentaire_general) <b>Entrée :</b> {{ $entree->commentaire_general }}<br> @endif
            @if ($sortie?->commentaire_general) <b>Sortie :</b> {{ $sortie->commentaire_general }} @endif
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <b>Entrée</b> — {{ $entree?->etablisseur?->nomComplet() }}
                @if ($entree?->signe_le) <br>signé le {{ $entree->signe_le->format('d/m/Y H:i') }} @endif
            </td>
            <td>
                <b>Sortie</b> — {{ $sortie?->etablisseur?->nomComplet() }}
                @if ($sortie?->signe_le) <br>signé le {{ $sortie->signe_le->format('d/m/Y H:i') }} @endif
            </td>
        </tr>
    </table>
</body>
</html>
