<?php

/*
| Comptes généraux et analytiques utilisés par l'export des écritures comptables
| (CdC § 9.3 « Écritures comptables », P3-CPT-06). Module déjà cadré pour Mon Gravier
| mais dont le fichier de cadrage (`PLAN-module-ecritures-comptables.md`) n'existe pas
| encore dans ce dépôt : les comptes ci-dessous sont un jeu minimal, paramétrable ici
| par famille (hébergement, extras, transferts, cautions, taxes), à ajuster avec le
| plan comptable réel de DALAKOUN SARL sans toucher au code de l'export.
*/

return [
    'comptes' => [
        'hebergement' => '706100',
        'extras' => '706200',
        'transferts' => '706300',
        'cautions' => '165000',
        'tva_collectee' => '445700',
        'tdt_collectee' => '447100',
        'taxe_sejour_collectee' => '447200',
        'clients' => '411000',
        'caisse' => '530000',
        'fournisseurs_partenaires' => '401000',
    ],
];
