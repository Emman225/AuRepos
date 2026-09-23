import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';

/// Faux serveur partagé par les tests de dépôts : répond ce qu'on lui dit et
/// retient la dernière requête. Copie du double utilisé par `client_api_test.dart`.
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
