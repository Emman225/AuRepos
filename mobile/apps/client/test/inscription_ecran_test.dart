import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_client/ecrans/inscription/inscription_ecran.dart';
import 'package:residences_client/fournisseurs.dart';
import 'package:residences_client/l10n/app_localizations.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import 'aide/faux_serveur.dart';

Widget monter(FauxServeur serveur) {
  final router = GoRouter(
    initialLocation: '/inscription',
    routes: [
      GoRoute(path: '/connexion', builder: (context, state) => const Scaffold(body: Text('CONNEXION'))),
      GoRoute(path: '/inscription', builder: (context, state) => const InscriptionEcran()),
    ],
  );

  return ProviderScope(
    overrides: [
      depotDeSessionProvider.overrideWithValue(DepotDeSessionEnMemoire()),
      depotAuthProvider.overrideWithValue(DepotAuth(clientDeTest(serveur))),
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

Future<void> _remplir(WidgetTester tester, {String motDePasseConfirmation = 'secretsecret', bool accepter = true}) async {
  await tester.enterText(find.widgetWithText(TextFormField, 'Nom'), 'Yao');
  await tester.enterText(find.widgetWithText(TextFormField, 'Prénoms'), 'Jean');
  await tester.enterText(find.widgetWithText(TextFormField, 'Email'), 'jean@exemple.ci');
  await tester.enterText(find.widgetWithText(TextFormField, 'Mot de passe'), 'secretsecret');
  await tester.enterText(find.widgetWithText(TextFormField, 'Confirmer le mot de passe'), motDePasseConfirmation);
  if (accepter) {
    await tester.tap(find.byType(CheckboxListTile));
  }
  await tester.pump();
}

void main() {
  testWidgets('une inscription réussie prévient et ramène vers la connexion', (tester) async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'data': {'email': 'jean@exemple.ci', 'code_valable_minutes': 15},
      'errors': null,
    });

    await tester.pumpWidget(monter(serveur));
    await _remplir(tester);

    await tester.ensureVisible(find.text('Créer mon compte'));
    await tester.tap(find.text('Créer mon compte'));
    await tester.pumpAndSettle();

    expect(find.text('CONNEXION'), findsOneWidget);
    expect(serveur.derniere!.path, '/auth/inscription');
    expect(serveur.derniere!.data, {
      'nom': 'Yao',
      'prenoms': 'Jean',
      'email': 'jean@exemple.ci',
      'mot_de_passe': 'secretsecret',
      'mot_de_passe_confirmation': 'secretsecret',
      'conditions_acceptees': true,
    });
  });

  testWidgets('refuse si les mots de passe diffèrent, sans appeler le serveur', (tester) async {
    final serveur = FauxServeur(200, {'success': true, 'message': '', 'data': null, 'errors': null});

    await tester.pumpWidget(monter(serveur));
    await _remplir(tester, motDePasseConfirmation: 'autrechose');

    await tester.ensureVisible(find.text('Créer mon compte'));
    await tester.tap(find.text('Créer mon compte'));
    await tester.pumpAndSettle();

    expect(find.text('Les mots de passe ne correspondent pas.'), findsOneWidget);
    expect(serveur.derniere, isNull);
  });

  testWidgets('refuse sans acceptation des conditions', (tester) async {
    final serveur = FauxServeur(200, {'success': true, 'message': '', 'data': null, 'errors': null});

    await tester.pumpWidget(monter(serveur));
    await _remplir(tester, accepter: false);

    await tester.ensureVisible(find.text('Créer mon compte'));
    await tester.tap(find.text('Créer mon compte'));
    await tester.pumpAndSettle();

    expect(find.text('Vous devez accepter les conditions générales.'), findsOneWidget);
    expect(serveur.derniere, isNull);
  });
}
