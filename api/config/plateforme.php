<?php

return [
    // Adresse du site React, pour les liens placés dans les courriels.
    // env() ne se lit QUE dans config/ : ailleurs il rend null dès que la configuration est en cache.
    'url_du_site' => rtrim(trim(explode(',', (string) env('FRONT_URLS', env('APP_URL', 'http://localhost:5173')))[0]), '/'),
];
