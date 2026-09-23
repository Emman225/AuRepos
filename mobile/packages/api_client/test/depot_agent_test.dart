import 'package:flutter_test/flutter_test.dart';
import 'package:residences_api_client/residences_api_client.dart';

import 'aide/faux_serveur.dart';

void main() {
  test('mesSejoursDuJour décode la liste de séjours', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': [
        {
          'id': 12,
          'reference': 'RES-000012',
          'etat': 'confirme',
          'etat_libelle': 'Confirmé',
          'client': {'id': 3, 'nom': 'Awa Kouassi', 'email': 'awa@exemple.ci', 'telephone': '+225 07 00 00 00'},
          'logement': {'id': 5, 'reference': 'LOG-5', 'nom': 'Studio Cocody', 'residence': 'Résidence Awa', 'residence_id': 1},
          'arrivee': '2026-10-01',
          'depart': '2026-10-05',
          'nombre_de_nuits': 4,
          'adultes': 2,
          'enfants': 0,
          'net_a_payer': 100000,
          'caution': 50000,
          'reglement': {'encaisse': 40000, 'reste_du': 60000},
          'arrive_le': null,
          'parti_le': null,
          'no_show_le': null,
          'caution_retenue': null,
          'caution_retenue_motif': null,
          'code_d_arrivee_emis': true,
        },
      ],
    });

    final sejours = await DepotAgent(clientDeTest(serveur)).mesSejoursDuJour();

    expect(serveur.derniere!.path, '/agent/sejours');
    expect(sejours, hasLength(1));
    expect(sejours.single.reference, 'RES-000012');
    expect(sejours.single.logement.nom, 'Studio Cocody');
    expect(sejours.single.estConfirme, isTrue);
    expect(sejours.single.reglement.resteDu, 60000);
  });

  test('faireLeCheckIn envoie le code saisi et décode le séjour arrivé', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': 'Check-in effectué : le séjour est arrivé.',
      'errors': null,
      'data': {
        'id': 12,
        'reference': 'RES-000012',
        'etat': 'arrive',
        'etat_libelle': 'Arrivé',
        'client': null,
        'logement': {'id': 5, 'reference': 'LOG-5', 'nom': 'Studio Cocody', 'residence': 'Résidence Awa', 'residence_id': 1},
        'arrivee': '2026-10-01',
        'depart': '2026-10-05',
        'nombre_de_nuits': 4,
        'adultes': 2,
        'enfants': 0,
        'net_a_payer': 100000,
        'caution': 50000,
        'reglement': {'encaisse': 40000, 'reste_du': 60000},
        'arrive_le': '01/10/2026 14:05:00',
        'parti_le': null,
        'no_show_le': null,
        'caution_retenue': null,
        'caution_retenue_motif': null,
        'code_d_arrivee_emis': true,
      },
    });

    final sejour = await DepotAgent(clientDeTest(serveur)).faireLeCheckIn(12, '4821');

    expect(serveur.derniere!.path, '/agent/sejours/12/check-in');
    expect(serveur.derniere!.data, {'code': '4821'});
    expect(sejour.estArrive, isTrue);
    expect(sejour.arriveLe, '01/10/2026 14:05:00');
  });

  test('faireLeCheckOut envoie la caution retenue et le motif', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {
        'id': 12,
        'reference': 'RES-000012',
        'etat': 'parti',
        'etat_libelle': 'Parti',
        'client': null,
        'logement': {'id': 5, 'reference': 'LOG-5', 'nom': 'Studio Cocody', 'residence': 'Résidence Awa', 'residence_id': 1},
        'arrivee': '2026-10-01',
        'depart': '2026-10-05',
        'nombre_de_nuits': 4,
        'adultes': 2,
        'enfants': 0,
        'net_a_payer': 100000,
        'caution': 50000,
        'reglement': {'encaisse': 100000, 'reste_du': 0},
        'arrive_le': '01/10/2026 14:05:00',
        'parti_le': '05/10/2026 11:00:00',
        'no_show_le': null,
        'caution_retenue': 5000,
        'caution_retenue_motif': 'Verre cassé',
        'code_d_arrivee_emis': true,
      },
    });

    final sejour = await DepotAgent(clientDeTest(serveur)).faireLeCheckOut(12, 5000, motif: 'Verre cassé');

    expect(serveur.derniere!.path, '/agent/sejours/12/check-out');
    expect(serveur.derniere!.data, {'caution_retenue': 5000, 'motif': 'Verre cassé'});
    expect(sejour.cautionRetenue, 5000);
    expect(sejour.cautionRetenueMotif, 'Verre cassé');
  });

  test('afficherLesConsommations décode le récapitulatif', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {'hebergement': 100000, 'repas': 5000, 'transferts': 0, 'total': 105000},
    });

    final consommations = await DepotAgent(clientDeTest(serveur)).afficherLesConsommations(12);

    expect(serveur.derniere!.path, '/agent/sejours/12/consommations');
    expect(consommations.total, 105000);
  });

  test('listerLesOccupants décode la fiche de police sans jamais le numéro de pièce', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': [
        {
          'id': 1,
          'nom': 'Kouassi',
          'prenoms': 'Awa',
          'enfant': false,
          'type_piece': 'CNI',
          'piece_fournie': true,
          'telephone': null,
          'pieces': [
            {'id': 9, 'statut': 'valide', 'nom_original': 'cni-awa.jpg'},
          ],
        },
      ],
    });

    final occupants = await DepotAgent(clientDeTest(serveur)).listerLesOccupants(12);

    expect(occupants.single.nomComplet, 'Kouassi Awa');
    expect(occupants.single.pieceFournie, isTrue);
    expect(occupants.single.pieces.single.nomOriginal, 'cni-awa.jpg');
  });

  test('etablirUnEtatDesLieux envoie le type et décode l’état créé', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {
        'id': 1,
        'type': 'entree',
        'type_libelle': 'Entrée',
        'commentaire_general': null,
        'signe': false,
        'signe_le': null,
        'etabli_par': 'Agent Test',
        'lignes': [],
      },
    });

    final etat = await DepotAgent(clientDeTest(serveur)).etablirUnEtatDesLieux(12, TypeEtatDesLieux.entree);

    expect(serveur.derniere!.path, '/agent/sejours/12/etats-des-lieux');
    expect(serveur.derniere!.data, {'type': 'entree'});
    expect(etat.type, TypeEtatDesLieux.entree);
    expect(etat.signe, isFalse);
  });

  test('ajouterUneLigneEtatDesLieux décode la ligne créée', () async {
    final serveur = FauxServeur(201, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {'id': 1, 'libelle': 'Salon', 'observation': 'Un peu sale', 'ordre': 1, 'photos': []},
    });

    final ligne = await DepotAgent(clientDeTest(serveur)).ajouterUneLigneEtatDesLieux(12, 1, 'Salon', observation: 'Un peu sale');

    expect(serveur.derniere!.path, '/agent/sejours/12/etats-des-lieux/1/lignes');
    expect(ligne.libelle, 'Salon');
  });

  test('signerUnEtatDesLieux envoie la signature en base64', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {
        'id': 1,
        'type': 'entree',
        'type_libelle': 'Entrée',
        'commentaire_general': null,
        'signe': true,
        'signe_le': '01/10/2026 14:10:00',
        'etabli_par': 'Agent Test',
        'lignes': [],
      },
    });

    final etat = await DepotAgent(clientDeTest(serveur)).signerUnEtatDesLieux(12, 1, 'data:image/png;base64,AAA');

    expect(serveur.derniere!.data, {'signature': 'data:image/png;base64,AAA'});
    expect(etat.signe, isTrue);
  });

  test('mesMissions filtre par statut quand demandé', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': [
        {
          'id': 4,
          'type': 'menage',
          'type_libelle': 'Ménage',
          'origine': 'avant_arrivee',
          'origine_libelle': 'Avant arrivée',
          'statut': 'a_faire',
          'statut_libelle': 'À faire',
          'logement': {'id': 5, 'nom': 'Studio Cocody', 'residence': 'Résidence Awa'},
          'sejour': {'reference': 'RES-000012', 'arrivee': '2026-10-01', 'depart': '2026-10-05'},
          'agent': 'Agent Test',
          'echeance': '2026-10-01 12:00:00',
          'notes': null,
          'debutee_le': null,
          'terminee_le': null,
          'created_at': null,
        },
      ],
    });

    final missions = await DepotAgent(clientDeTest(serveur)).mesMissions(statut: StatutDeMission.aFaire);

    expect(serveur.derniere!.path, '/agent/missions');
    expect(serveur.derniere!.queryParameters, {'statut': 'a_faire'});
    expect(missions.single.logement?.nom, 'Studio Cocody');
    expect(missions.single.statut, StatutDeMission.aFaire);
  });

  test('demarrerUneMission puis terminerUneMission avec des notes', () async {
    final serveurDebut = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {
        'id': 4,
        'type': 'menage',
        'type_libelle': 'Ménage',
        'origine': 'avant_arrivee',
        'origine_libelle': 'Avant arrivée',
        'statut': 'en_cours',
        'statut_libelle': 'En cours',
        'logement': null,
        'sejour': null,
        'agent': 'Agent Test',
        'echeance': '2026-10-01 12:00:00',
        'notes': null,
        'debutee_le': '01/10/2026 09:00:00',
        'terminee_le': null,
        'created_at': null,
      },
    });

    final mission = await DepotAgent(clientDeTest(serveurDebut)).demarrerUneMission(4);
    expect(serveurDebut.derniere!.path, '/agent/missions/4/debut');
    expect(mission.statut, StatutDeMission.enCours);

    final serveurFin = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {
        'id': 4,
        'type': 'menage',
        'type_libelle': 'Ménage',
        'origine': 'avant_arrivee',
        'origine_libelle': 'Avant arrivée',
        'statut': 'faite',
        'statut_libelle': 'Faite',
        'logement': null,
        'sejour': null,
        'agent': 'Agent Test',
        'echeance': '2026-10-01 12:00:00',
        'notes': 'Tout est prêt',
        'debutee_le': '01/10/2026 09:00:00',
        'terminee_le': '01/10/2026 09:40:00',
        'created_at': null,
      },
    });

    final terminee = await DepotAgent(clientDeTest(serveurFin)).terminerUneMission(4, notes: 'Tout est prêt');
    expect(serveurFin.derniere!.path, '/agent/missions/4/fin');
    expect(serveurFin.derniere!.data, {'notes': 'Tout est prêt'});
    expect(terminee.statut, StatutDeMission.faite);
    expect(terminee.notes, 'Tout est prêt');
  });
}
