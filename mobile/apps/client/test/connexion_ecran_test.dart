import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_client/ecrans/connexion/connexion_ecran.dart';
import 'package:residences_client/fournisseurs.dart';
import 'package:residences_client/l10n/app_localizations.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import 'aide/faux_serveur.dart';

Widget monter(FauxServeur serveur, DepotDeSessionEnMemoire depot) {
  final router = GoRouter(
    initialLocation: '/connexion',
    routes: [
      GoRoute(path: '/', builder: (context, state) => const Scaffold(body: Text('ACCUEIL'))),
      GoRoute(path: '/connexion', builder: (context, state) => const ConnexionEcran()),
      GoRoute(path: '/inscription', builder: (context, state) => const Scaffold(body: Text('INSCRIPTION'))),
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

/// Se résout immédiatement à « personne connecté » : évite toute course
/// entre le build initial de sessionProvider et l'appel à definir() déclenché
/// par la connexion, sans dépendre du minutage réel des futures.
class _SessionDeTest extends SessionNotifier {
  @override
  Future<Utilisateur?> build() async => null;
}

void main() {
  testWidgets('une connexion réussie range le jeton et mène à l’accueil', (tester) async {
    final depot = DepotDeSessionEnMemoire();
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': {
        'jeton': 'jeton-abc',
        'type': 'Bearer',
        'expire_dans': 3600,
        'utilisateur': {
          'id': 1,
          'nom': 'Kouassi',
          'prenoms': 'Awa',
          'nom_complet': 'Awa Kouassi',
          'email': 'awa@exemple.ci',
          'telephone': null,
          'profil': 'client',
        },
      },
    });

    await tester.pumpWidget(monter(serveur, depot));

    await tester.enterText(find.widgetWithText(TextFormField, 'Email ou téléphone'), 'awa@exemple.ci');
    await tester.enterText(find.widgetWithText(TextFormField, 'Mot de passe'), 'secretsecret');
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();

    expect(find.text('ACCUEIL'), findsOneWidget);
    expect(await depot.lireJeton(), 'jeton-abc');
    expect(serveur.derniere!.data, {'identifiant': 'awa@exemple.ci', 'mot_de_passe': 'secretsecret'});
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

    await tester.enterText(find.widgetWithText(TextFormField, 'Email ou téléphone'), 'awa@exemple.ci');
    await tester.enterText(find.widgetWithText(TextFormField, 'Mot de passe'), 'mauvais');
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();

    expect(find.text('Identifiants incorrects.'), findsOneWidget);
    expect(await depot.lireJeton(), isNull);
    // Toujours sur l'écran de connexion, pas de navigation.
    expect(find.text('ACCUEIL'), findsNothing);
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

  testWidgets('mène vers l’inscription', (tester) async {
    final depot = DepotDeSessionEnMemoire();
    final serveur = FauxServeur(200, {'success': true, 'message': '', 'data': null, 'errors': null});

    await tester.pumpWidget(monter(serveur, depot));

    await tester.tap(find.text('Créer un compte'));
    await tester.pumpAndSettle();

    expect(find.text('INSCRIPTION'), findsOneWidget);
  });
}
