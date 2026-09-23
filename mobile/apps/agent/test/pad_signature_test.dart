import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_agent/widgets/pad_signature.dart';
import 'package:residences_ui/residences_ui.dart';

Widget monter(ValueChanged<String> onValider) => MaterialApp(
  theme: ThemeResidences.clair,
  locale: const Locale('fr'),
  localizationsDelegates: Libelles.localizationsDelegates,
  supportedLocales: Libelles.supportedLocales,
  home: Scaffold(body: PadSignature(onValider: onValider)),
);

void main() {
  testWidgets('le bouton valider reste désactivé tant que rien n’est dessiné', (tester) async {
    await tester.pumpWidget(monter((_) {}));

    final bouton = tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Valider la signature'));
    expect(bouton.onPressed, isNull);
    expect(find.text('Faites signer avant de valider.'), findsOneWidget);
  });

  testWidgets('dessiner un trait puis valider produit un PNG encodé en base64', (tester) async {
    String? signature;
    await tester.pumpWidget(monter((s) => signature = s));

    await tester.runAsync(() async {
      await tester.drag(find.byKey(const ValueKey('zone-signature')), const Offset(60, 20));
      await tester.pump();

      final bouton = tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Valider la signature'));
      expect(bouton.onPressed, isNotNull);

      await tester.tap(find.widgetWithText(FilledButton, 'Valider la signature'));
      await tester.pump();

      // `_valider` est asynchrone (`toImage` puis `toByteData`, du vrai travail de rasterisation)
      // et `pumpAndSettle` ne l'attend pas : il rend la main dès qu'aucune image n'est planifiée,
      // alors que la future est encore en vol. Dans `runAsync`, ce délai s'écoule réellement.
      await Future<void>.delayed(const Duration(milliseconds: 200));
      await tester.pump();
    });

    expect(signature, isNotNull);
    expect(signature, startsWith('data:image/png;base64,'));
  });

  testWidgets('effacer retire le trait dessiné', (tester) async {
    await tester.pumpWidget(monter((_) {}));

    await tester.drag(find.byKey(const ValueKey('zone-signature')), const Offset(60, 20));
    await tester.pump();
    expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Valider la signature')).onPressed, isNotNull);

    await tester.tap(find.text('Effacer'));
    await tester.pump();

    expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Valider la signature')).onPressed, isNull);
  });
}
