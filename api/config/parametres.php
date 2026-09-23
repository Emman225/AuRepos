<?php

/*
| Définition des paramètres de la plateforme (CdC § 12), onglet par onglet.
|
| Ici : le libellé, le type, la valeur par défaut et les règles de chaque
| paramètre. En base (table `parametres`) : uniquement les valeurs modifiées.
| Mon Gravier rangeait tout dans une table à ligne unique de ~60 colonnes,
| une migration par nouveau réglage ; ajouter un paramètre ne demande ici
| qu'une entrée dans ce fichier.
|
| Types : entier, decimal, booleen, texte, texte_long, heure, liste, administrateur.
| `public`  : lisible sans connexion (GET /api/v1/configuration).
| `reserve` : modifiable par le seul super administrateur.
|
| Rappel : un paramètre ne touche jamais un séjour déjà enregistré — taux et
| prix sont figés sur le séjour à la réservation (CdC § 5.4).
*/

return [

    'general' => [
        'libelle' => 'Configuration générale',
        'parametres' => [
            'general.nom_plateforme' => ['libelle' => 'Nom de la plateforme', 'type' => 'texte', 'defaut' => 'Résidences meublées', 'regles' => ['required', 'max:100'], 'public' => true],
            'general.devise' => ['libelle' => 'Devise', 'type' => 'texte', 'defaut' => 'F', 'regles' => ['required', 'max:10'], 'public' => true],
            'general.telephone' => ['libelle' => 'Téléphone de contact', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:30'], 'public' => true],
            'general.whatsapp' => ['libelle' => 'Numéro WhatsApp (bouton flottant)', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:30'], 'public' => true],
            'general.courriel' => ['libelle' => 'Courriel de contact', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'email'], 'public' => true],
            'general.fidelite_montant_par_point' => ['libelle' => 'Montant encaissé pour 1 point de fidélité', 'type' => 'entier', 'defaut' => 1000, 'regles' => ['required', 'integer', 'min:1']],
            'general.fidelite_valeur_du_point' => ['libelle' => 'Valeur d’un point de fidélité', 'type' => 'entier', 'defaut' => 10, 'regles' => ['required', 'integer', 'min:0']],
            'general.minimum_a_payer' => ['libelle' => 'Minimum à payer sur un séjour', 'type' => 'entier', 'defaut' => 1000, 'regles' => ['required', 'integer', 'min:0'], 'aide' => 'Un séjour ne tombe jamais à 0 F, même avec des points ou un code promo.'],
            'general.plafond_paiement_en_ligne' => ['libelle' => 'Plafond du paiement en ligne', 'type' => 'entier', 'defaut' => 2000000, 'regles' => ['required', 'integer', 'min:0'], 'public' => true, 'aide' => 'Au-delà, seuls l’agence et le virement sont proposés.'],
            'general.site_en_construction' => ['libelle' => 'Mode « site en construction »', 'type' => 'booleen', 'defaut' => false, 'regles' => ['required', 'boolean'], 'public' => true, 'reserve' => true],
        ],
    ],

    'sejours' => [
        'libelle' => 'Séjours',
        'parametres' => [
            'sejours.heure_arrivee' => ['libelle' => 'Heure d’arrivée par défaut', 'type' => 'heure', 'defaut' => '14:00', 'regles' => ['required', 'date_format:H:i'], 'public' => true],
            'sejours.heure_depart' => ['libelle' => 'Heure de départ par défaut', 'type' => 'heure', 'defaut' => '12:00', 'regles' => ['required', 'date_format:H:i'], 'public' => true],
            'sejours.duree_minimale' => ['libelle' => 'Durée minimale par défaut (nuits)', 'type' => 'entier', 'defaut' => 1, 'regles' => ['required', 'integer', 'min:1', 'max:365']],
            'sejours.taux_acompte' => ['libelle' => 'Acompte exigé pour confirmer (%)', 'type' => 'decimal', 'defaut' => 30, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
            'sejours.delai_expiration_heures' => ['libelle' => 'Expiration d’une demande non réglée (heures)', 'type' => 'entier', 'defaut' => 24, 'regles' => ['required', 'integer', 'min:1', 'max:720'], 'aide' => 'Passé ce délai, la demande est annulée et les dates sont libérées.'],
            'sejours.heure_no_show' => ['libelle' => 'Heure de bascule en no-show (J+1)', 'type' => 'heure', 'defaut' => '12:00', 'regles' => ['required', 'date_format:H:i']],
            'sejours.vente_par_defaut' => ['libelle' => 'Mode de vente par défaut', 'type' => 'liste', 'defaut' => 'logement', 'options' => ['logement' => 'Par logement nommé', 'type' => 'Par type de logement'], 'regles' => ['required', 'in:logement,type']],
            // Annulation : GRATUITE jusqu'à N jours avant l'arrivée ; passé ce délai, le pourcentage est retenu.
            'sejours.annulation_flexible_delai' => ['libelle' => 'Annulation flexible : gratuite jusqu’à N jours avant l’arrivée', 'type' => 'entier', 'defaut' => 1, 'regles' => ['required', 'integer', 'min:0', 'max:365']],
            'sejours.annulation_moderee_delai' => ['libelle' => 'Annulation modérée : gratuite jusqu’à N jours avant l’arrivée', 'type' => 'entier', 'defaut' => 5, 'regles' => ['required', 'integer', 'min:0', 'max:365']],
            'sejours.annulation_stricte_delai' => ['libelle' => 'Annulation stricte : gratuite jusqu’à N jours avant l’arrivée', 'type' => 'entier', 'defaut' => 14, 'regles' => ['required', 'integer', 'min:0', 'max:365']],
            'sejours.annulation_flexible' => ['libelle' => 'Annulation flexible : retenue (%)', 'type' => 'decimal', 'defaut' => 50, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
            'sejours.annulation_moderee' => ['libelle' => 'Annulation modérée : retenue (%)', 'type' => 'decimal', 'defaut' => 50, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
            'sejours.annulation_stricte' => ['libelle' => 'Annulation stricte : retenue (%)', 'type' => 'decimal', 'defaut' => 100, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
        ],
    ],

    'taxes' => [
        'libelle' => 'Taxes',
        'parametres' => [
            'taxes.tva' => ['libelle' => 'Taux de TVA (%)', 'type' => 'decimal', 'defaut' => 18, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
            'taxes.tva_sur_transferts' => ['libelle' => 'Appliquer la TVA aux transferts', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'taxes.tdt' => ['libelle' => 'Taxe de développement touristique (%)', 'type' => 'decimal', 'defaut' => 3, 'regles' => ['required', 'numeric', 'min:0', 'max:100'], 'aide' => 'Calculée sur le net TTC, portée en « autres taxes » sur la facture.'],
            'taxes.sejour_montant' => ['libelle' => 'Taxe de séjour : montant par nuitée', 'type' => 'entier', 'defaut' => 0, 'regles' => ['required', 'integer', 'min:0'], 'aide' => 'À renseigner selon la commune. 0 = aucune taxe de séjour.'],
            'taxes.sejour_base' => ['libelle' => 'Taxe de séjour : base de calcul', 'type' => 'liste', 'defaut' => 'occupant', 'options' => ['occupant' => 'Par nuitée et par occupant', 'logement' => 'Par nuitée et par logement'], 'regles' => ['required', 'in:occupant,logement']],
            'taxes.sejour_enfants_exoneres' => ['libelle' => 'Exonérer les enfants de la taxe de séjour', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'taxes.retenue_personne_physique' => ['libelle' => 'Retenue à la source : personnes physiques (%)', 'type' => 'decimal', 'defaut' => 7.5, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
            'taxes.retenue_entreprise_hors_reel' => ['libelle' => 'Retenue à la source : entreprises hors régime réel (%)', 'type' => 'decimal', 'defaut' => 2, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
        ],
    ],

    'creances' => [
        'libelle' => 'Créances et relances',
        'parametres' => [
            // Délai et seuil paramétrables (CdC § 9.1) : la file de relance ne liste que les
            // comptes dont le reste dû dépasse ce seuil, depuis plus de ce délai après le départ.
            'creances.relance_delai_jours' => ['libelle' => 'Relance : délai après le départ (jours)', 'type' => 'entier', 'defaut' => 15, 'regles' => ['required', 'integer', 'min:0', 'max:365']],
            'creances.relance_seuil_montant' => ['libelle' => 'Relance : seuil de reste dû (F CFA)', 'type' => 'entier', 'defaut' => 5000, 'regles' => ['required', 'integer', 'min:0']],
        ],
    ],

    'gestionnaires' => [
        'libelle' => 'Gestionnaires et notifications',
        'parametres' => [
            'gestionnaires.validant_1_id' => ['libelle' => 'Gestionnaire validant 1', 'type' => 'administrateur', 'defaut' => null, 'regles' => ['nullable', 'integer']],
            'gestionnaires.validant_2_id' => ['libelle' => 'Gestionnaire validant 2 (trésorier)', 'type' => 'administrateur', 'defaut' => null, 'regles' => ['nullable', 'integer', 'different:valeurs.validant_1_id'], 'aide' => 'Lui seul confirme une réduction ou un geste commercial. Sans trésorier désigné, aucune réduction n’est possible.'],
            'gestionnaires.courriels_notification' => ['libelle' => 'Adresses de notification (séparées par des virgules)', 'type' => 'texte_long', 'defaut' => null, 'regles' => ['nullable', 'max:1000']],
            'gestionnaires.canal_courriel' => ['libelle' => 'Notifier par courriel', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'gestionnaires.canal_sms' => ['libelle' => 'Notifier par SMS', 'type' => 'booleen', 'defaut' => false, 'regles' => ['required', 'boolean']],
            'gestionnaires.canal_whatsapp' => ['libelle' => 'Notifier par WhatsApp', 'type' => 'booleen', 'defaut' => false, 'regles' => ['required', 'boolean']],
        ],
    ],

    'messages' => [
        'libelle' => 'Modèles de messages',
        'parametres' => [
            // Jetons disponibles, communs aux quatre modèles : {{ client }} {{ reference }} {{ logement }} {{ arrivee }} {{ depart }} {{ lien }}
            'messages.confirmation_sujet' => ['libelle' => 'Confirmation — sujet (courriel)', 'type' => 'texte', 'defaut' => 'Votre réservation {{ reference }} est enregistrée', 'regles' => ['required', 'max:200']],
            'messages.confirmation_corps' => ['libelle' => 'Confirmation — message', 'type' => 'texte_long', 'defaut' => "Bonjour {{ client }},\n\nVotre réservation {{ reference }} pour {{ logement }} du {{ arrivee }} au {{ depart }} est enregistrée. Retrouvez tous les détails dans votre espace : {{ lien }}", 'regles' => ['required', 'max:2000'], 'aide' => 'Jetons disponibles : {{ client }} {{ reference }} {{ logement }} {{ arrivee }} {{ depart }} {{ lien }}'],
            'messages.consignes_arrivee_sujet' => ['libelle' => 'Consignes d’arrivée — sujet (courriel)', 'type' => 'texte', 'defaut' => 'Vos consignes d’arrivée pour le séjour {{ reference }}', 'regles' => ['required', 'max:200']],
            'messages.consignes_arrivee_corps' => ['libelle' => 'Consignes d’arrivée — message', 'type' => 'texte_long', 'defaut' => "Bonjour {{ client }},\n\nVotre code d’arrivée et les consignes d’accès pour {{ logement }} vous attendent dans votre espace, à consulter avant le {{ arrivee }} : {{ lien }}", 'regles' => ['required', 'max:2000'], 'aide' => 'Jetons disponibles : {{ client }} {{ reference }} {{ logement }} {{ arrivee }} {{ depart }} {{ lien }}'],
            'messages.rappel_j1_sujet' => ['libelle' => 'Rappel J-1 — sujet (courriel)', 'type' => 'texte', 'defaut' => 'Votre séjour {{ reference }} commence demain', 'regles' => ['required', 'max:200']],
            'messages.rappel_j1_corps' => ['libelle' => 'Rappel J-1 — message', 'type' => 'texte_long', 'defaut' => "Bonjour {{ client }},\n\nVotre séjour à {{ logement }} commence demain, {{ arrivee }}. Pensez à consulter vos consignes d’arrivée : {{ lien }}", 'regles' => ['required', 'max:2000'], 'aide' => 'Jetons disponibles : {{ client }} {{ reference }} {{ logement }} {{ arrivee }} {{ depart }} {{ lien }}'],
            'messages.demande_avis_j1_sujet' => ['libelle' => 'Demande d’avis J+1 — sujet (courriel)', 'type' => 'texte', 'defaut' => 'Un mot sur votre séjour {{ reference }} ?', 'regles' => ['required', 'max:200']],
            'messages.demande_avis_j1_corps' => ['libelle' => 'Demande d’avis J+1 — message', 'type' => 'texte_long', 'defaut' => "Bonjour {{ client }},\n\nVotre séjour à {{ logement }} est terminé. Votre avis compte : {{ lien }}", 'regles' => ['required', 'max:2000'], 'aide' => 'Jetons disponibles : {{ client }} {{ reference }} {{ logement }} {{ arrivee }} {{ depart }} {{ lien }}'],
        ],
    ],

    'comptant' => [
        'libelle' => 'Comptant / Agence',
        'parametres' => [
            'comptant.especes' => ['libelle' => 'Accepter les espèces', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'comptant.mobile_money' => ['libelle' => 'Accepter le mobile money', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'comptant.carte' => ['libelle' => 'Accepter la carte bancaire', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'comptant.virement' => ['libelle' => 'Accepter le virement', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'comptant.cheque' => ['libelle' => 'Accepter le chèque', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'comptant.paiement_en_ligne_actif' => ['libelle' => 'Paiement en ligne actif', 'type' => 'booleen', 'defaut' => false, 'regles' => ['required', 'boolean'], 'public' => true],
        ],
    ],

    'proprietaires' => [
        'libelle' => 'Propriétaires',
        'parametres' => [
            // Double validation obligatoire (CdC § 7.3) : ne se change PAS par ce formulaire, voir PourcentageEntreprise.
            'proprietaires.pourcentage_entreprise' => ['libelle' => 'Pourcentage entreprise par défaut (%)', 'type' => 'decimal', 'defaut' => 20, 'regles' => ['required', 'numeric', 'min:0', 'max:500'], 'aide' => 'Plancher de marge conseillé sur le prix propriétaire.', 'double_validation' => true],
            'proprietaires.commission' => ['libelle' => 'Commission par défaut, mode commission (%)', 'type' => 'decimal', 'defaut' => 20, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
            'proprietaires.jour_releves' => ['libelle' => 'Jour de génération des relevés', 'type' => 'entier', 'defaut' => 1, 'regles' => ['required', 'integer', 'min:1', 'max:28']],
            'proprietaires.part_entreprise_cautions' => ['libelle' => 'Part de l’entreprise sur les cautions retenues (%)', 'type' => 'decimal', 'defaut' => 0, 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
            'proprietaires.regime_fiscal_obligatoire' => ['libelle' => 'Régime fiscal et justificatif obligatoires avant tout reversement', 'type' => 'booleen', 'defaut' => true, 'regles' => ['required', 'boolean']],
            'proprietaires.photos_minimum' => ['libelle' => 'Nombre minimal de photos par logement', 'type' => 'entier', 'defaut' => 10, 'regles' => ['required', 'integer', 'min:1', 'max:100']],
            'proprietaires.photos_maximum' => ['libelle' => 'Nombre maximal de photos par logement', 'type' => 'entier', 'defaut' => 30, 'regles' => ['required', 'integer', 'min:1', 'max:100', 'gte:valeurs.photos_minimum']],
            'proprietaires.photo_taille_max_mo' => ['libelle' => 'Taille maximale d’une photo (Mo)', 'type' => 'entier', 'defaut' => 5, 'regles' => ['required', 'integer', 'min:1', 'max:20']],
            'proprietaires.filigrane' => ['libelle' => 'Texte du filigrane des photos', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:60']],
            'proprietaires.validateur_publications_id' => ['libelle' => 'Validateur désigné des publications', 'type' => 'administrateur', 'defaut' => null, 'regles' => ['nullable', 'integer']],
            'proprietaires.ecart_mediane_seuil' => ['libelle' => 'Seuil de signalement « tarif hors médiane » (%)', 'type' => 'decimal', 'defaut' => 30, 'regles' => ['required', 'numeric', 'min:0', 'max:1000'], 'aide' => 'Un tarif propriétaire écarté de la médiane de son type au-delà de ce pourcentage est signalé, jamais bloqué (CdC § 7.3).'],
        ],
    ],

    'conditions' => [
        'libelle' => 'Termes et conditions',
        'parametres' => [
            'conditions.cgv' => ['libelle' => 'Conditions générales de vente', 'type' => 'texte_long', 'defaut' => null, 'regles' => ['nullable', 'max:100000'], 'public' => true],
            'conditions.regles_maison' => ['libelle' => 'Règles de la maison par défaut', 'type' => 'texte_long', 'defaut' => null, 'regles' => ['nullable', 'max:20000'], 'public' => true],
            'conditions.confidentialite' => ['libelle' => 'Politique de confidentialité', 'type' => 'texte_long', 'defaut' => null, 'regles' => ['nullable', 'max:100000'], 'public' => true],
            'conditions.conservation_pieces_mois' => ['libelle' => 'Conservation des pièces d’identité (mois)', 'type' => 'entier', 'defaut' => 12, 'regles' => ['required', 'integer', 'min:1', 'max:120']],
        ],
    ],

    'entreprise' => [
        'libelle' => 'Entreprise',
        'parametres' => [
            'entreprise.raison_sociale' => ['libelle' => 'Raison sociale', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:150']],
            'entreprise.ncc' => ['libelle' => 'NCC (numéro de compte contribuable)', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:30'], 'aide' => 'Encodé dans le QR code des factures : à renseigner AVANT la première facture.'],
            'entreprise.logo_url' => ['libelle' => 'Logo (URL de l’image)', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'string', 'max:500'], 'aide' => 'Affiché en en-tête des factures PDF.'],
            'entreprise.regime_imposition' => ['libelle' => 'Régime d’imposition', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:60']],
            'entreprise.centre_impots' => ['libelle' => 'Centre des impôts', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:100']],
            'entreprise.rccm' => ['libelle' => 'RCCM', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:60']],
            'entreprise.references_bancaires' => ['libelle' => 'Références bancaires', 'type' => 'texte_long', 'defaut' => null, 'regles' => ['nullable', 'max:500']],
            'entreprise.siege' => ['libelle' => 'Siège', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:200']],
            'entreprise.telephone' => ['libelle' => 'Téléphone', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:30']],
            'entreprise.courriel' => ['libelle' => 'Courriel', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'email']],
            'entreprise.capital' => ['libelle' => 'Capital', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:60']],
            'entreprise.cnps' => ['libelle' => 'Numéro CNPS', 'type' => 'texte', 'defaut' => null, 'regles' => ['nullable', 'max:60']],
        ],
    ],
];
