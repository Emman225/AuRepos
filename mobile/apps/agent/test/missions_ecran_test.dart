import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_agent/ecrans/missions/missions_ecran.dart';
import 'package:residences_agent/fournisseurs.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import 'aide/faux_serveur.dart';

/// Répond à `GET /agent/missions` avec la liste courante, et accepte n'importe quel
/// `POST .../debut` ou `.../fin` en renvoyant la mission mise à jour fournie.
final class _FauxServeurMissions implements HttpClientAdapter {
  _FauxServeurMissions(this._liste);

  List<Map<String, Object?>> _liste;
  RequestOptions? derniere;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    derniere = options;

    if (options.path.endsWith('/debut')) {
      _liste = [jsonMission(statut: 'en_cours', statutLibelle: 'En cours')];
    } else if (options.path.endsWith('/fin')) {
      _liste = [jsonMission(statut: 'faite', statutLibelle: 'Faite')];
    }

    final Object donnees = options.path == '/agent/missions'
        ? _liste
        : _liste.first;

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

Widget _monter(_FauxServeurMissions serveur) {
  final dio = Dio()..httpClientAdapter = serveur;
  final client = ClientApi(urlDeBase: 'http://api.test', session: DepotDeSessionEnMemoire(), dio: dio);

  return ProviderScope(
    overrides: [depotAgentProvider.overrideWithValue(DepotAgent(client))],
    child: MaterialApp(
      theme: ThemeResidences.clair,
      locale: const Locale('fr'),
      localizationsDelegates: Libelles.localizationsDelegates,
      supportedLocales: Libelles.supportedLocales,
      home: const MissionsEcran(),
    ),
  );
}

void main() {
  testWidgets('affiche mes missions et démarre celle qui est à faire', (tester) async {
    final serveur = _FauxServeurMissions([jsonMission()]);

    await tester.pumpWidget(_monter(serveur));
    await tester.pumpAndSettle();

    expect(find.text('Ménage'), findsOneWidget);
    expect(find.text('À faire'), findsOneWidget);

    await tester.tap(find.text('Démarrer'));
    await tester.pumpAndSettle();

    expect(find.text('En cours'), findsOneWidget);
    expect(find.text('Terminer'), findsOneWidget);
  });

  testWidgets('termine une mission en cours avec des notes', (tester) async {
    final serveur = _FauxServeurMissions([jsonMission(statut: 'en_cours', statutLibelle: 'En cours')]);

    await tester.pumpWidget(_monter(serveur));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Terminer').first);
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextField, 'Notes (optionnel)'), 'Tout est fait');
    await tester.tap(find.widgetWithText(FilledButton, 'Terminer').last);
    await tester.pumpAndSettle();

    expect(find.text('Faite'), findsOneWidget);
  });

  testWidgets('affiche un état vide quand aucune mission n’est affectée', (tester) async {
    final serveur = _FauxServeurMissions([]);

    await tester.pumpWidget(_monter(serveur));
    await tester.pumpAndSettle();

    expect(find.text('Aucune mission.'), findsOneWidget);
  });
}
