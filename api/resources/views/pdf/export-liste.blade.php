<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
    <style>
        /* Charte : bleu nuit #1E3A5F, sable #D9C3A5, blanc cassé #F8F7F4. DejaVu : seule police sûre de dompdf pour les accents. */
        @page { margin: 10mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #1F2A37; }
        h1 { color: #1E3A5F; font-size: 15px; margin: 0 0 3px; }
        .discret { color: #5B6676; font-size: 8px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #1E3A5F; color: #FFFFFF; text-align: left; padding: 4px 5px; font-size: 8px; }
        tbody td { padding: 4px 5px; border-bottom: 1px solid #E4DED3; }
        tbody tr:nth-child(even) td { background: #F8F7F4; }
        .vide { color: #5B6676; font-style: italic; padding: 10px 0; }
    </style>
</head>
<body>
    <h1>{{ $titre }}</h1>
    <div class="discret">Exporté le {{ now()->format('d/m/Y à H:i:s') }} — {{ count($lignes) }} ligne(s)</div>

    @if (count($lignes) === 0)
        <p class="vide">Aucune ligne pour cette période et ces filtres.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($colonnes as $libelle)
                        <th>{{ $libelle }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($lignes as $ligne)
                    <tr>
                        @foreach (array_keys($colonnes) as $cle)
                            <td>{{ $ligne[$cle] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
