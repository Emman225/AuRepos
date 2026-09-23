import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_agent/ecrans/sejour_detail/sejour_detail_ecran.dart';
import 'package:residences_agent/fournisseurs.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import 'aide/faux_serveur.dart';

/// Distingue les quatre routes que la fiche détail appelle (séjour, occupants, états des
/// lieux, check-in) par leur chemin — nécessaire car la page combine plusieurs sections.
final class _FauxServeurDetail implements HttpClientAdapter {
  _FauxServeurDetail(this._sejour);

  Map<String, Object?> _sejour;

  /// La requête de check-in, retenue à part : la fiche se RECHARGE juste après, et une simple
  /// « dernière requête reçue » serait ce rechargement, un GET sans corps.
  RequestOptions? checkIn;

  /// Tous les chemins appelés, dans l'ordre : dit ce que la page a réellement demandé quand une
  /// attente échoue, au lieu de laisser deviner.
  final List<String> chemins = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    chemins.add(options.path);
    final Object donnees;

    if (options.path.endsWith('/occupants') || options.path.endsWith('/etats-des-lieux')) {
      donnees = <Object?>[];
    } else if (options.path.endsWith('/check-in')) {
      checkIn = options;
      _sejour = jsonSejourAgent(etat: 'arrive', etatLibelle: 'Arrivé');
      donnees = _sejour;
    } else {
      donnees = _sejour;
    }

    return ResponseBody.fromString(
      jsonEncode({'success': true, 'message': '', 'errors': null, 'data': donnees}),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Widget _monter(_FauxServeurDetail serveur) {
  final dio = Dio()..httpClientAdapter = serveur;
  final client = ClientApi(urlDeBase: 'http://api.test', session: DepotDeSessionEnMemoire(), dio: dio);

  return ProviderScope(
    overrides: [depotAgentProvider.overrideWithValue(DepotAgent(client))],
    child: MaterialApp(
      theme: ThemeResidences.clair,
      locale: const Locale('fr'),
      localizationsDelegates: Libelles.localizationsDelegates,
      supportedLocales: Libelles.supportedLocales,
      home: const SejourDetailEcran(id: 12),
    ),
  );
}

void main() {
  testWidgets('affiche le détail d’un séjour confirmé avec le bouton check-in', (tester) async {
    final serveur = _FauxServeurDetail(jsonSejourAgent(etat: 'confirme', etatLibelle: 'Confirmé'));

    await tester.pumpWidget(_monter(serveur));
    await tester.pumpAndSettle();

    // La référence paraît deux fois par construction : dans la barre d'application, qui reste
    // visible une fois la page défilée, et dans l'en-tête de la fiche à côté de la pastille d'état.
    expect(find.text('RES-000012'), findsNWidgets(2));
    expect(find.text('Studio Cocody — Résidence Awa'), findsOneWidget);
    expect(find.text('Faire le check-in'), findsOneWidget);
    expect(find.text('Faire le check-out'), findsNothing);
  });

  testWidgets('le check-in saisi met à jour l’état affiché à « Arrivé »', (tester) async {
    final serveur = _FauxServeurDetail(jsonSejourAgent(etat: 'confirme', etatLibelle: 'Confirmé'));

    await tester.pumpWidget(_monter(serveur));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Faire le check-in'));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextField, 'Code d’arrivée'), '4821');
    // `enterText` ne redessine PAS : sans ce pump, le bouton est encore celui de l'image
    // précédente, désactivé tant que le champ était vide, et le tap ne déclenche rien — en
    // silence, un bouton désactivé n'émettant aucune erreur.
    await tester.pump();

    await tester.tap(find.widgetWithText(FilledButton, 'Confirmer le check-in'));
    await tester.pumpAndSettle();

    expect(serveur.checkIn!.data, {'code': '4821'}, reason: 'chemins appelés : ${serveur.chemins}');
    expect(find.text('Arrivé'), findsOneWidget);
    expect(find.text('Faire le check-out'), findsOneWidget);
  });

  testWidgets('un séjour parti affiche la caution retenue et son motif', (tester) async {
    final serveur = _FauxServeurDetail(
      jsonSejourAgent(etat: 'parti', etatLibelle: 'Parti', cautionRetenue: 5000, cautionRetenueMotif: 'Verre cassé'),
    );

    await tester.pumpWidget(_monter(serveur));
    await tester.pumpAndSettle();

    expect(find.text('Caution retenue : ${Formats.montant(5000)}'), findsOneWidget);
    expect(find.text('Verre cassé'), findsOneWidget);
    expect(find.text('Faire le check-in'), findsNothing);
    expect(find.text('Faire le check-out'), findsNothing);
  });
}
