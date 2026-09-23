import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_agent/ecrans/gains/gains_ecran.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_ui/residences_ui.dart';

void main() {
  testWidgets('affiche un état « bientôt disponible » sans appeler le réseau', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeResidences.clair,
        locale: const Locale('fr'),
        localizationsDelegates: Libelles.localizationsDelegates,
        supportedLocales: Libelles.supportedLocales,
        home: const GainsEcran(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Mes gains'), findsOneWidget);
    expect(find.text('Le suivi de vos gains n’est pas encore disponible dans cette version.'), findsOneWidget);
  });
}
