import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_agent/ecrans/tableau_de_bord/tableau_de_bord_ecran.dart';
import 'package:residences_agent/fournisseurs.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import 'aide/faux_serveur.dart';

const _utilisateur = Utilisateur(
  id: 1,
  nom: 'Kouassi',
  prenoms: 'Awa',
  nomComplet: 'Awa Kouassi',
  email: 'awa@exemple.ci',
  profil: profilAgentTerrain,
);

class _SessionConnectee extends SessionNotifier {
  @override
  Future<Utilisateur?> build() async => _utilisateur;
}

/// Le tableau de bord interroge deux routes en une seule page
/// (`/agent/sejours` et `/agent/missions`) : ce double répond différemment selon le chemin.
final class _FauxServeurTableauDeBord implements HttpClientAdapter {
  RequestOptions? derniere;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    derniere = options;
    final Object donnees = options.path.contains('missions')
        ? [jsonMission(statut: 'a_faire'), jsonMission(id: 5, statut: 'faite', statutLibelle: 'Faite')]
        : [jsonSejourAgent(etat: 'confirme'), jsonSejourAgent(id: 13, etat: 'arrive', etatLibelle: 'Arrivé')];

    return ResponseBody.fromString(
      jsonEncode({'success': true, 'message': '', 'errors': null, 'data': donnees}),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Widget _monter(_FauxServeurTableauDeBord serveur) {
  final dio = Dio()..httpClientAdapter = serveur;
  final client = ClientApi(urlDeBase: 'http://api.test', session: DepotDeSessionEnMemoire(), dio: dio);

  final router = GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(path: '/', builder: (context, state) => const TableauDeBordEcran()),
      GoRoute(path: '/sejours', builder: (context, state) => const Scaffold(body: Text('SEJOURS'))),
      GoRoute(path: '/missions', builder: (context, state) => const Scaffold(body: Text('MISSIONS'))),
      GoRoute(path: '/gains', builder: (context, state) => const Scaffold(body: Text('GAINS'))),
    ],
  );

  return ProviderScope(
    overrides: [
      depotAgentProvider.overrideWithValue(DepotAgent(client)),
      sessionProvider.overrideWith(_SessionConnectee.new),
    ],
    child: MaterialApp.router(
      theme: ThemeResidences.clair,
      locale: const Locale('fr'),
      localizationsDelegates: Libelles.localizationsDelegates,
      supportedLocales: Libelles.supportedLocales,
      routerConfig: router,
    ),
  );
}

void main() {
  testWidgets('affiche les compteurs dérivés des séjours et missions du jour', (tester) async {
    await tester.pumpWidget(_monter(_FauxServeurTableauDeBord()));
    await tester.pumpAndSettle();

    expect(find.text('Bonjour, Awa'), findsOneWidget);
    // 1 confirmé (à accueillir), 1 arrivé (à faire sortir), 1 mission encore à faire.
    expect(find.text('1'), findsNWidgets(3));
  });

  testWidgets('un tap sur le compteur missions mène à la liste des missions', (tester) async {
    await tester.pumpWidget(_monter(_FauxServeurTableauDeBord()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Missions à faire'));
    await tester.pumpAndSettle();

    expect(find.text('MISSIONS'), findsOneWidget);
  });
}
