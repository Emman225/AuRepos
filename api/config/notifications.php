<?php

return [
    /*
    | Passerelles actives. « essai » ne contacte aucun service : elle journalise seulement,
    | pour le développement et les tests. Basculer une fois un prestataire réel choisi et
    | ses identifiants fournis (décision D12 — voir PLAN-REALISATION.md).
    */
    'sms_passerelle' => env('SMS_PASSERELLE', 'essai'),
    'whatsapp_passerelle' => env('WHATSAPP_PASSERELLE', 'essai'),

    // Une notification restée « en attente » ou « échouée » au-delà de ce délai est reprise par la tâche planifiée.
    'reprise_minutes' => (int) env('NOTIFICATIONS_REPRISE_MINUTES', 15),
    'tentatives_max' => (int) env('NOTIFICATIONS_TENTATIVES_MAX', 5),

    /*
    | SMS : passerelle HTTP générique (POST JSON), pour s'adapter à n'importe quel
    | prestataire sans réécrire de code — seuls l'URL et les noms de champs changent.
    */
    'sms' => [
        'url' => env('SMS_URL'),
        'cle_api' => env('SMS_CLE_API'),
        'expediteur' => env('SMS_EXPEDITEUR', 'RESIDENCES'),
        'champ_destinataire' => env('SMS_CHAMP_DESTINATAIRE', 'to'),
        'champ_message' => env('SMS_CHAMP_MESSAGE', 'message'),
        'champ_expediteur' => env('SMS_CHAMP_EXPEDITEUR', 'from'),
        'timeout' => (int) env('SMS_TIMEOUT', 15),
    ],

    /*
    | WhatsApp : Meta Cloud API (l'intégration la plus courante ; changer d'implémentation
    | si un autre fournisseur est retenu — le contrat PasserelleDeMessage ne change pas).
    */
    'whatsapp' => [
        'url' => env('WHATSAPP_URL', 'https://graph.facebook.com/v20.0'),
        'numero_id' => env('WHATSAPP_NUMERO_ID'),
        'jeton' => env('WHATSAPP_JETON'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    ],
];
