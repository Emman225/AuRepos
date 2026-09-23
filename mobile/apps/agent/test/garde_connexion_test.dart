import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_agent/fournisseurs.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_agent/widgets/garde_connexion.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

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

class _SessionAnonyme extends SessionNotifier {
  @override
  Future<Utilisateur?> build() async => null;
}

Widget monter(SessionNotifier Function() session) {
  final router = GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(
        path: '/',
        builder: (context, state) => GardeConnexion(builder: (context, ref) => const Scaffold(body: Text('PROTEGE'))),
      ),
      GoRoute(path: '/connexion', builder: (context, state) => const Scaffold(body: Text('CONNEXION'))),
    ],
  );

  return ProviderScope(
    overrides: [sessionProvider.overrideWith(session)],
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
  testWidgets('affiche l’écran protégé quand un agent est connecté', (tester) async {
    await tester.pumpWidget(monter(_SessionConnectee.new));
    await tester.pumpAndSettle();

    expect(find.text('PROTEGE'), findsOneWidget);
  });

  testWidgets('renvoie vers la connexion quand personne n’est connecté', (tester) async {
    await tester.pumpWidget(monter(_SessionAnonyme.new));
    await tester.pumpAndSettle();

    expect(find.text('CONNEXION'), findsOneWidget);
    expect(find.text('PROTEGE'), findsNothing);
  });
}
