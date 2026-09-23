import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_client/ecrans/accueil/accueil_ecran.dart';
import 'package:residences_client/fournisseurs.dart';
import 'package:residences_client/l10n/app_localizations.dart';
import 'package:residences_ui/residences_ui.dart';

const _logement = VignetteLogement(
  reference: 'LOG-1',
  nom: 'Studio Cocody',
  residence: 'Résidence Awa',
  resume: 'Studio calme',
  lieu: Lieu(commune: 'Cocody', quartier: 'Angré'),
  capaciteMaximale: 2,
  prixParNuit: 25000,
  photo: null,
  noteMoyenne: 4.5,
);

Widget monter(List<Override> overrides, {Locale langue = const Locale('fr')}) {
  final router = GoRouter(routes: [GoRoute(path: '/', builder: (context, state) => const AccueilEcran())]);
  return ProviderScope(
    overrides: [sessionProvider.overrideWith(() => _SessionDeTestVide()), ...overrides],
    child: MaterialApp.router(
      theme: ThemeResidences.clair,
      locale: langue,
      localizationsDelegates: Libelles.localizationsDelegates,
      supportedLocales: Libelles.supportedLocales,
      routerConfig: router,
    ),
  );
}

class _SessionDeTestVide extends SessionNotifier {
  @override
  Future<Utilisateur?> build() async => null;
}

void main() {
  testWidgets('affiche les logements mis en avant renvoyés par l’API', (tester) async {
    await tester.pumpWidget(
      monter([
        donneesAccueilProvider.overrideWith(
          (ref) async => const DonneesAccueil(
            carrousel: [],
            misesEnAvant: [_logement],
            residencesMisesEnAvant: [],
            residencesMieuxNotees: [],
            bannieres: [],
            temoignages: [],
          ),
        ),
      ]),
    );
    await tester.pumpAndSettle();

    expect(find.text('Studio Cocody'), findsOneWidget);
    expect(find.text('Angré, Cocody'), findsOneWidget);
    expect(find.textContaining('nuit'), findsOneWidget);
  });

  testWidgets('affiche un message quand rien n’est mis en avant', (tester) async {
    await tester.pumpWidget(
      monter([
        donneesAccueilProvider.overrideWith(
          (ref) async => const DonneesAccueil(
            carrousel: [],
            misesEnAvant: [],
            residencesMisesEnAvant: [],
            residencesMieuxNotees: [],
            bannieres: [],
            temoignages: [],
          ),
        ),
      ]),
    );
    await tester.pumpAndSettle();

    expect(find.text('Aucun logement mis en avant pour le moment.'), findsOneWidget);
  });

  testWidgets('affiche le message en clair quand le serveur ne répond pas', (tester) async {
    await tester.pumpWidget(
      monter([
        donneesAccueilProvider.overrideWith(
          (ref) async => throw const ErreurApi('Pas de connexion internet. Vérifiez le réseau puis réessayez.'),
        ),
      ]),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('Pas de connexion internet'), findsOneWidget);
    expect(find.text('Réessayer'), findsOneWidget);
  });

  testWidgets('propose la connexion quand personne n’est connecté', (tester) async {
    await tester.pumpWidget(
      monter([donneesAccueilProvider.overrideWith((ref) async => throw const ErreurApi('indisponible'))]),
    );
    await tester.pumpAndSettle();

    expect(find.text('Connexion'), findsOneWidget);
    expect(find.byIcon(Icons.logout), findsNothing);
  });

  testWidgets('se traduit en anglais', (tester) async {
    await tester.pumpWidget(
      monter([
        donneesAccueilProvider.overrideWith(
          (ref) async => const DonneesAccueil(
            carrousel: [],
            misesEnAvant: [],
            residencesMisesEnAvant: [],
            residencesMieuxNotees: [],
            bannieres: [],
            temoignages: [],
          ),
        ),
      ], langue: const Locale('en')),
    );
    await tester.pumpAndSettle();

    expect(find.text('No featured homes right now.'), findsOneWidget);
  });
}
