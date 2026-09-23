import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';

/// Faux serveur pour les tests d'écrans qui appellent un dépôt directement plutôt que via
/// un FutureProvider surchargeable. Copie du double utilisé par `apps/client/test/`.
final class FauxServeur implements HttpClientAdapter {
  FauxServeur(this.statut, this.corps);

  final int statut;
  final Map<String, Object?> corps;
  RequestOptions? derniere;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    derniere = options;
    return ResponseBody.fromString(
      jsonEncode(corps),
      statut,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

ClientApi clientDeTest(FauxServeur serveur, {DepotDeSession? session}) {
  final dio = Dio()..httpClientAdapter = serveur;
  return ClientApi(urlDeBase: 'http://api.test', session: session ?? DepotDeSessionEnMemoire(), dio: dio);
}

Map<String, Object?> jsonUtilisateur({String profil = 'agent_terrain'}) => {
  'id': 1,
  'nom': 'Kouassi',
  'prenoms': 'Awa',
  'nom_complet': 'Awa Kouassi',
  'email': 'awa@exemple.ci',
  'telephone': null,
  'profil': profil,
};

Map<String, Object?> jsonSejourAgent({
  int id = 12,
  String reference = 'RES-000012',
  String etat = 'confirme',
  String etatLibelle = 'Confirmé',
  String? noShowLe,
  num? cautionRetenue,
  String? cautionRetenueMotif,
}) => {
  'id': id,
  'reference': reference,
  'etat': etat,
  'etat_libelle': etatLibelle,
  'client': {'id': 3, 'nom': 'Jean Yao', 'email': 'jean@exemple.ci', 'telephone': '+225 07 00 00 00'},
  'logement': {'id': 5, 'reference': 'LOG-5', 'nom': 'Studio Cocody', 'residence': 'Résidence Awa', 'residence_id': 1},
  'arrivee': '2026-10-01',
  'depart': '2026-10-05',
  'nombre_de_nuits': 4,
  'adultes': 2,
  'enfants': 0,
  'net_a_payer': 100000,
  'caution': 50000,
  'reglement': {'encaisse': 40000, 'reste_du': 60000},
  'arrive_le': null,
  'parti_le': null,
  'no_show_le': noShowLe,
  'caution_retenue': cautionRetenue,
  'caution_retenue_motif': cautionRetenueMotif,
  'code_d_arrivee_emis': true,
};

Map<String, Object?> jsonMission({
  int id = 4,
  String statut = 'a_faire',
  String statutLibelle = 'À faire',
}) => {
  'id': id,
  'type': 'menage',
  'type_libelle': 'Ménage',
  'origine': 'avant_arrivee',
  'origine_libelle': 'Avant arrivée',
  'statut': statut,
  'statut_libelle': statutLibelle,
  'logement': {'id': 5, 'nom': 'Studio Cocody', 'residence': 'Résidence Awa'},
  'sejour': {'reference': 'RES-000012', 'arrivee': '2026-10-01', 'depart': '2026-10-05'},
  'agent': 'Agent Test',
  'echeance': '2026-10-01 12:00:00',
  'notes': null,
  'debutee_le': null,
  'terminee_le': null,
  'created_at': null,
};
