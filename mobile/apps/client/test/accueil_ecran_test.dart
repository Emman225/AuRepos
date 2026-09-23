import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_client/ecrans/accueil/accueil_ecran.dart';
import 'package:residences_client/l10n/app_localizations.dart';
import 'package:residences_ui/residences_ui.dart';

Widget monter(Override etat, {Locale langue = const Locale('fr')}) => ProviderScope(
  overrides: [etat],
  child: MaterialApp(
    theme: ThemeResidences.clair,
    locale: langue,
    localizationsDelegates: Libelles.localizationsDelegates,
    supportedLocales: Libelles.supportedLocales,
    home: const AccueilEcran(),
  ),
);

void main() {
  testWidgets('affiche l’état de l’API renvoyé par le serveur', (tester) async {
    await tester.pumpWidget(
      monter(etatApiProvider.overrideWith((ref) async => (version: 'v1', baseJoignable: true))),
    );
    await tester.pumpAndSettle();

    expect(find.text('API en service — v1'), findsOneWidget);
    expect(find.text('Base de données joignable'), findsOneWidget);
  });

  testWidgets('affiche le message en clair quand le serveur ne répond pas', (tester) async {
    await tester.pumpWidget(
      monter(
        etatApiProvider.overrideWith(
          (ref) async => throw const ErreurApi('Pas de connexion internet. Vérifiez le réseau puis réessayez.'),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('Pas de connexion internet'), findsOneWidget);
    expect(find.text('Réessayer'), findsOneWidget);
  });

  testWidgets('se traduit en anglais', (tester) async {
    await tester.pumpWidget(
      monter(
        etatApiProvider.overrideWith((ref) async => (version: 'v1', baseJoignable: false)),
        langue: const Locale('en'),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Service status'), findsOneWidget);
    expect(find.text('Database unreachable'), findsOneWidget);
  });
}
