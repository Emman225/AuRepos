import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';

/// Faux serveur : répond ce qu'on lui dit et retient la dernière requête.
/// La couche réseau de Mon Gravier n'était pas injectable, donc jamais testée.
final class FauxServeur implements HttpClientAdapter {
  FauxServeur(this.statut, this.corps);

  final int statut;
  final Map<String, Object?> corps;
  RequestOptions? derniere;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    derniere = options;
    return ResponseBody.fromString(
      jsonEncode(corps),
      statut,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

ClientApi client(FauxServeur serveur, DepotDeSession session, {void Function()? expiree}) {
  final dio = Dio()..httpClientAdapter = serveur;
  return ClientApi(urlDeBase: 'http://api.test', session: session, surSessionExpiree: expiree, dio: dio);
}

void main() {
  test('déplie l’enveloppe et rend directement data', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'data': {'version_api': 'v1'},
      'errors': null,
    });

    final data = await client(serveur, DepotDeSessionEnMemoire()).lire<Map<String, dynamic>>('/etat');

    expect(data['version_api'], 'v1');
  });

  test('joint le jeton en en-tête Authorization, jamais dans le corps', () async {
    final session = DepotDeSessionEnMemoire();
    await session.ecrireJeton('jeton-abc');
    final serveur = FauxServeur(200, {'success': true, 'message': '', 'data': 1, 'errors': null});

    await client(serveur, session).envoyer<int>('/sejours', corps: {'logement': 4});

    expect(serveur.derniere!.headers['Authorization'], 'Bearer jeton-abc');
    expect(jsonEncode(serveur.derniere!.data), isNot(contains('jeton-abc')));
  });

  test('rend le message du serveur et le détail par champ sur un 422', () async {
    final serveur = FauxServeur(422, {
      'success': false,
      'message': 'Certaines informations sont incorrectes.',
      'data': null,
      'errors': {
        'nom': ['Le nom est obligatoire.'],
      },
    });

    await expectLater(
      client(serveur, DepotDeSessionEnMemoire()).envoyer<void>('/x'),
      throwsA(
        isA<ErreurApi>()
            .having((e) => e.statut, 'statut', 422)
            .having((e) => e.message, 'message', 'Certaines informations sont incorrectes.')
            .having((e) => e.champs?['nom'], 'champs', ['Le nom est obligatoire.']),
      ),
    );
  });

  test('sur un 401, efface le jeton et prévient l’application', () async {
    final session = DepotDeSessionEnMemoire();
    await session.ecrireJeton('vieux-jeton');
    var prevenue = false;
    final serveur = FauxServeur(401, {'success': false, 'message': 'Vous devez vous connecter.', 'data': null, 'errors': null});

    await expectLater(
      client(serveur, session, expiree: () => prevenue = true).lire<void>('/moi'),
      throwsA(isA<ErreurApi>().having((e) => e.sessionExpiree, 'sessionExpiree', isTrue)),
    );

    expect(prevenue, isTrue);
    expect(await session.lireJeton(), isNull);
  });
}
