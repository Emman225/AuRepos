<?php

return [
    // Police du filigrane. DejaVu est libre et livrée avec dompdf : elle existe donc
    // sur le serveur comme sur les postes (Arial, elle, n'existe pas sous Linux).
    'police' => env('PHOTOS_POLICE', base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf')),
];
