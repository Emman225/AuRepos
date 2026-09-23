import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_ui/residences_ui.dart';

import 'l10n/app_localizations.dart';
import 'routeur.dart';

void main() => runApp(const ProviderScope(child: ApplicationAgent()));

class ApplicationAgent extends StatelessWidget {
  const ApplicationAgent({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      onGenerateTitle: (context) => Libelles.of(context).marque,
      theme: ThemeResidences.clair,
      localizationsDelegates: Libelles.localizationsDelegates,
      supportedLocales: Libelles.supportedLocales,
      locale: const Locale('fr'),
      routerConfig: routeur,
    );
  }
}
