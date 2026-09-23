import 'package:flutter_test/flutter_test.dart';
import 'package:residences_api_client/residences_api_client.dart';

import 'aide/faux_serveur.dart';

void main() {
  test('connexion décode le jeton et l’utilisateur', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'data': {
        'jeton': 'abc123',
        'type': 'Bearer',
        'expire_dans': 3600,
        'utilisateur': {
          'id': 7,
          'nom': 'Kouassi',
          'prenoms': 'Awa',
          'nom_complet': 'Awa Kouassi',
          'email': 'awa@exemple.ci',
          'telephone': '+225 07 00 00 00',
          'profil': 'client',
        },
      },
      'errors': null,
    });

    final depot = DepotAuth(clientDeTest(serveur));
    final session = await depot.connexion(identifiant: 'awa@exemple.ci', motDePasse: 'secret');

    expect(session.jeton, 'abc123');
    expect(session.utilisateur.nomComplet, 'Awa Kouassi');
    expect(session.utilisateur.profil, 'client');

    // Envoie identifiant/mot_de_passe, jamais email/password : mêmes noms que le web.
    expect(serveur.derniere!.path, '/auth/connexion');
    expect(serveur.derniere!.data, {'identifiant': 'awa@exemple.ci', 'mot_de_passe': 'secret'});
  });

  test('inscription décode l’attente de vérification', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'data': {'email': 'nouveau@exemple.ci', 'code_valable_minutes': 15},
      'errors': null,
    });

    final depot = DepotAuth(clientDeTest(serveur));
    final attente = await depot.inscription(
      nom: 'Yao',
      prenoms: 'Jean',
      email: 'nouveau@exemple.ci',
      motDePasse: 'secretsecret',
      motDePasseConfirmation: 'secretsecret',
      conditionsAcceptees: true,
    );

    expect(attente.email, 'nouveau@exemple.ci');
    expect(attente.codeValableMinutes, 15);
    expect(serveur.derniere!.path, '/auth/inscription');
  });

  test('remonte une ErreurApi en clair sur un identifiant invalide', () async {
    final serveur = FauxServeur(422, {
      'success': false,
      'message': 'Identifiants incorrects.',
      'data': null,
      'errors': {
        'identifiant': ['Identifiants incorrects.'],
      },
    });

    final depot = DepotAuth(clientDeTest(serveur));

    await expectLater(
      depot.connexion(identifiant: 'x@x.ci', motDePasse: 'mauvais'),
      throwsA(isA<ErreurApi>().having((e) => e.message, 'message', 'Identifiants incorrects.')),
    );
  });
}
