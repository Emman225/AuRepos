<?php

/*
| Seuls les domaines du front React déclarés dans FRONT_URLS peuvent appeler
| l'API depuis un navigateur. Les applications Flutter ne sont pas concernées
| (CORS est une règle de navigateur). Dans Mon Gravier, cette configuration
| couvrait `api/*` alors que les routes vivaient ailleurs : elle ne protégeait rien.
*/

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('FRONT_URLS', ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600,
    // Jeton JWT en en-tête Authorization : aucun cookie à partager.
    'supports_credentials' => false,
];
