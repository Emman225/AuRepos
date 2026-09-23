import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_agent/ecrans/connexion/connexion_ecran.dart';
import 'package:residences_agent/fournisseurs.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import 'aide/faux_serveur.dart';

Widget monter(FauxServeur serveur, DepotDeSessionEnMemoire depot) {
  final router = GoRouter(
    initialLocation: '/connexion',
    routes: [
      GoRoute(path: '/', builder: (context, state) => const Scaffold(body: Text('TABLEAU DE BORD'))),
      GoRoute(path: '/connexion', builder: (context, state) => const ConnexionEcran()),
    ],
  );

  return ProviderScope(
    overrides: [
      depotDeSessionProvider.overrideWithValue(depot),
      depotAuthProvider.overrideWithValue(DepotAuth(clientDeTest(serveur, session: depot))),
      sessionProvider.overrideWith(_SessionDeTest.new),
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

/// Se résout immédiatement à « personne connecté » : évite toute course entre le build
/// initial de sessionProvider et l'appel à definir() déclenché par la connexion.
class _SessionDeTest extends SessionNotifier {
  @override
  Future<Utilisateur?> build() async => null;
}

void main() {
  testWidgets('une connexion réussie d’un compte agent_terrain range le jeton et mène au tableau de bord', (tester) async {
    final depot = DepotDeSessionEnMemoire();
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {'jeton': 'jeton-abc', 'type': 'Bearer', 'expire_dans': 3600, 'utilisateur': jsonUtilisateur()},
    });

    await tester.pumpWidget(monter(serveur, depot));

    await tester.enterText(find.widgetWithText(TextFormField, 'Email ou téléphone'), 'agent@exemple.ci');
    await tester.enterText(find.widgetWithText(TextFormField, 'Mot de passe'), 'secretsecret');
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();

    expect(find.text('TABLEAU DE BORD'), findsOneWidget);
    expect(await depot.lireJeton(), 'jeton-abc');
  });

  testWidgets('un compte d’un autre profil est refusé, sans jeton rangé ni navigation', (tester) async {
    final depot = DepotDeSessionEnMemoire();
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {
        'jeton': 'jeton-client',
        'type': 'Bearer',
        'expire_dans': 3600,
        'utilisateur': jsonUtilisateur(profil: 'client'),
      },
    });

    await tester.pumpWidget(monter(serveur, depot));

    await tester.enterText(find.widgetWithText(TextFormField, 'Email ou téléphone'), 'client@exemple.ci');
    await tester.enterText(find.widgetWithText(TextFormField, 'Mot de passe'), 'secretsecret');
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();

    expect(find.text('Ce compte n’est pas un compte agent de terrain.'), findsOneWidget);
    expect(await depot.lireJeton(), isNull);
    expect(find.text('TABLEAU DE BORD'), findsNothing);
  });

  testWidgets('affiche le message du serveur sur un identifiant invalide', (tester) async {
    final depot = DepotDeSessionEnMemoire();
    final serveur = FauxServeur(422, {
      'success': false,
      'message': 'Identifiants incorrects.',
      'data': null,
      'errors': {
        'identifiant': ['Identifiants incorrects.'],
      },
    });

    await tester.pumpWidget(monter(serveur, depot));

    await tester.enterText(find.widgetWithText(TextFormField, 'Email ou téléphone'), 'agent@exemple.ci');
    await tester.enterText(find.widgetWithText(TextFormField, 'Mot de passe'), 'mauvais');
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();

    expect(find.text('Identifiants incorrects.'), findsOneWidget);
    expect(await depot.lireJeton(), isNull);
  });

  testWidgets('refuse de soumettre un formulaire vide', (tester) async {
    final depot = DepotDeSessionEnMemoire();
    final serveur = FauxServeur(200, {'success': true, 'message': '', 'data': null, 'errors': null});

    await tester.pumpWidget(monter(serveur, depot));

    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();

    expect(find.text('Ce champ est obligatoire.'), findsNWidgets(2));
    expect(serveur.derniere, isNull);
  });
}
