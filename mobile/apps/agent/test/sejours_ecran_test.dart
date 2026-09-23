import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_agent/ecrans/sejours/sejours_ecran.dart';
import 'package:residences_agent/fournisseurs.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import 'aide/faux_serveur.dart';

Widget monter(FauxServeur serveur) {
  final router = GoRouter(
    initialLocation: '/sejours',
    routes: [
      GoRoute(path: '/sejours', builder: (context, state) => const SejoursEcran()),
      GoRoute(path: '/sejours/:id', builder: (context, state) => Scaffold(body: Text('DETAIL ${state.pathParameters['id']}'))),
    ],
  );

  return ProviderScope(
    overrides: [depotAgentProvider.overrideWithValue(DepotAgent(clientDeTest(serveur)))],
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
  testWidgets('affiche la liste des séjours du jour et mène au détail', (tester) async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': [jsonSejourAgent()],
    });

    await tester.pumpWidget(monter(serveur));
    await tester.pumpAndSettle();

    expect(serveur.derniere!.path, '/agent/sejours');
    expect(find.text('RES-000012'), findsOneWidget);
    expect(find.text('Studio Cocody'), findsOneWidget);

    await tester.tap(find.text('RES-000012'));
    await tester.pumpAndSettle();

    expect(find.text('DETAIL 12'), findsOneWidget);
  });

  testWidgets('affiche un état vide quand aucun séjour n’est dû aujourd’hui', (tester) async {
    final serveur = FauxServeur(200, {'success': true, 'message': '', 'errors': null, 'data': <Object?>[]});

    await tester.pumpWidget(monter(serveur));
    await tester.pumpAndSettle();

    expect(find.text('Aucun séjour aujourd’hui.'), findsOneWidget);
  });
}
