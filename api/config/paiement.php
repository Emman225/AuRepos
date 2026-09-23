<?php

return [
    /*
    | Passerelle active. « essai » ne contacte aucun service : elle sert au développement et aux tests.
    | Bascule sur « paysecure » quand les clés sont fournies.
    */
    'passerelle' => env('PAIEMENT_PASSERELLE', 'essai'),

    // Un paiement non confirmé au bout de ce délai est déclaré expiré et ses dates libérées.
    'expiration_minutes' => (int) env('PAIEMENT_EXPIRATION_MINUTES', 30),

    'paysecure' => [
        // Ces clés ne quittent JAMAIS le serveur : le navigateur ne reçoit que l'URL de la passerelle.
        'url' => env('PAYSECURE_URL'),
        'cle_api' => env('PAYSECURE_CLE_API'),
        'marchand' => env('PAYSECURE_MARCHAND'),
        // Secret partagé qui signe les rappels : sans lui, un rappel n'est pas authentifié.
        'secret_rappel' => env('PAYSECURE_SECRET_RAPPEL'),
        'timeout' => (int) env('PAYSECURE_TIMEOUT', 20),
    ],
];
