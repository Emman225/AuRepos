<?php

/**
 * Facture normalisée électronique (FNE, DGI Côte d'Ivoire — CdC § 9.4, 12).
 * Contrat vérifié sur le SDK officieux PRODESTIC/fne-sdk-php (endpoints, en-têtes, formes JSON),
 * pas deviné : POST {base_url}/external/invoices/sign et POST {base_url}/external/invoices/{id}/refund,
 * authentification par en-tête `Authorization: Bearer <clé>`.
 */
return [
    // false = aucune transmission n'est tentée ; les factures restent au statut « non configurée ».
    'enabled' => (bool) env('FNE_ENABLED', false),

    'base_url' => env('FNE_BASE_URL'),
    'api_key' => env('FNE_API_KEY'),
    'timeout' => (int) env('FNE_TIMEOUT', 20),

    'default_template' => env('FNE_DEFAULT_TEMPLATE', 'B2C'),
    'default_payment_method' => env('FNE_DEFAULT_PAYMENT_METHOD', 'cash'),
    'default_tax' => env('FNE_DEFAULT_TAX', 'TVA'),

    'point_of_sale' => env('FNE_POINT_OF_SALE'),
    'establishment' => env('FNE_ESTABLISHMENT'),

    // true = une facture non transmissible (DGI en échec) bloque la suite du circuit qui l'a demandée.
    // false = elle reste « à transmettre » et une nouvelle tentative peut être faite plus tard.
    'block_on_failure' => (bool) env('FNE_BLOCK_ON_FAILURE', false),

    // Sous ce solde de stickers restants, chaque transmission déclenche une alerte aux administrateurs.
    'seuil_alerte_stickers' => (int) env('FNE_SEUIL_ALERTE_STICKERS', 50),
];
