import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_client/ecrans/recherche/recherche_ecran.dart';
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

Widget monter(List<Override> overrides) {
  final router = GoRouter(routes: [GoRoute(path: '/', builder: (context, state) => const RechercheEcran())]);
  return ProviderScope(
    overrides: [
      communesProvider.overrideWith((ref) async => const [CommuneChoix(id: 1, nom: 'Cocody')]),
      ...overrides,
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
  testWidgets('n’appelle pas la recherche avant que l’utilisateur ne la lance', (tester) async {
    await tester.pumpWidget(monter([]));
    await tester.pumpAndSettle();

    expect(find.text('Aucun logement ne correspond à votre recherche.'), findsNothing);
    expect(find.byType(CircularProgressIndicator), findsNothing);
  });

  testWidgets('recherche par commune et affiche les résultats', (tester) async {
    await tester.pumpWidget(
      monter([
        resultatsRechercheProvider(
          const CriteresRecherche(communeId: 1),
        ).overrideWith(
          (ref) async => const ResultatsRecherche(
            avecDates: false,
            arrivee: '',
            depart: '',
            elements: [_logement],
            pagination: Pagination(page: 1, parPage: 12, total: 1, dernierePage: 1),
          ),
        ),
      ]),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byType(DropdownButtonFormField<int?>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cocody'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Rechercher'));
    await tester.pumpAndSettle();

    expect(find.text('Studio Cocody'), findsOneWidget);
    expect(find.text('1 logement(s) trouvé(s)'), findsOneWidget);
  });

  testWidgets('affiche le message « aucun résultat »', (tester) async {
    await tester.pumpWidget(
      monter([
        resultatsRechercheProvider(const CriteresRecherche()).overrideWith(
          (ref) async => const ResultatsRecherche(
            avecDates: false,
            arrivee: '',
            depart: '',
            elements: [],
            pagination: Pagination(page: 1, parPage: 12, total: 0, dernierePage: 1),
          ),
        ),
      ]),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Rechercher'));
    await tester.pumpAndSettle();

    expect(find.text('Aucun logement ne correspond à votre recherche.'), findsOneWidget);
  });
}
