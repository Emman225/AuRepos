import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_agent/ecrans/sejour_detail/section_occupants.dart';
import 'package:residences_agent/fournisseurs.dart';
import 'package:residences_agent/l10n/app_localizations.dart';
import 'package:residences_agent/services/selecteur_de_photo.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

/// Renvoie toujours le même fichier de test, sans jamais toucher l'appareil photo réel —
/// c'est tout l'intérêt de faire de [SelecteurDePhoto] une interface remplaçable.
final class _SelecteurDePhotoDeTest implements SelecteurDePhoto {
  _SelecteurDePhotoDeTest(this.chemin);
  final String chemin;

  @override
  Future<String?> choisir() async => chemin;
}

final class _FauxServeurOccupants implements HttpClientAdapter {
  /// Le dépôt de la pièce, retenu à part : la section RECHARGE la liste juste après
  /// (`ref.invalidate`), et une simple « dernière requête reçue » serait ce rechargement.
  RequestOptions? depotDeLaPiece;
  var _pieceFournie = false;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    if (options.path.endsWith('/piece')) {
      depotDeLaPiece = options;
      _pieceFournie = true;
    }

    final donnees = [
      {
        'id': 1,
        'nom': 'Kouassi',
        'prenoms': 'Awa',
        'enfant': false,
        'type_piece': 'CNI',
        'piece_fournie': _pieceFournie,
        'telephone': '+225 07 00 00 00',
        'pieces': _pieceFournie
            ? [
                {'id': 9, 'statut': 'en_attente', 'nom_original': 'cni-awa.jpg'},
              ]
            : <Object?>[],
      },
    ];

    return ResponseBody.fromString(
      jsonEncode({
        'success': true,
        'message': '',
        'errors': null,
        'data': options.path.endsWith('/piece') ? donnees.single : donnees,
      }),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  testWidgets('ajouter une photo dépose la pièce et met à jour la fiche', (tester) async {
    final fichier = File('${Directory.systemTemp.path}/occupant-test-${DateTime.now().microsecondsSinceEpoch}.jpg')
      ..writeAsBytesSync([0xFF, 0xD8, 0xFF]);
    addTearDown(() => fichier.deleteSync());

    final serveur = _FauxServeurOccupants();
    final dio = Dio()..httpClientAdapter = serveur;
    final client = ClientApi(urlDeBase: 'http://api.test', session: DepotDeSessionEnMemoire(), dio: dio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          depotAgentProvider.overrideWithValue(DepotAgent(client)),
          selecteurDePhotoProvider.overrideWithValue(_SelecteurDePhotoDeTest(fichier.path)),
        ],
        child: MaterialApp(
          theme: ThemeResidences.clair,
          locale: const Locale('fr'),
          localizationsDelegates: Libelles.localizationsDelegates,
          supportedLocales: Libelles.supportedLocales,
          home: const Scaffold(body: SectionOccupants(sejourId: 12)),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Aucune pièce déposée.'), findsOneWidget);

    // Le dépôt lit un VRAI fichier du disque (`MultipartFile`) : hors `runAsync`, cette
    // entrée-sortie ne s'achève jamais, l'indicateur d'attente tourne sans fin et
    // `pumpAndSettle` finit par abandonner. `runAsync` laisse le temps réel s'écouler.
    await tester.runAsync(() async {
      await tester.tap(find.text('Ajouter une photo'));
      await tester.pump();

      // Le dépôt, puis le rechargement de la liste qu'il déclenche (`ref.invalidate`), passent
      // tous deux par de vraies futures : on laisse le temps réel s'écouler entre deux images.
      await Future<void>.delayed(const Duration(milliseconds: 300));
      await tester.pump();
      await Future<void>.delayed(const Duration(milliseconds: 300));
      await tester.pump();
    });

    await tester.pumpAndSettle();

    expect(serveur.depotDeLaPiece!.path, '/agent/sejours/12/occupants/1/piece');
    expect(find.text('cni-awa.jpg'), findsOneWidget);
  });
}
