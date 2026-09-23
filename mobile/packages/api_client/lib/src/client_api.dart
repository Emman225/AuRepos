import 'package:dio/dio.dart';
import 'package:residences_core/residences_core.dart';

import 'erreur_api.dart';

/// Point de passage unique vers l'API.
///
/// - le jeton part en en-tête `Authorization`, jamais dans le corps ;
/// - l'enveloppe `{success, message, data, errors}` est dépliée ici ;
/// - toute panne ressort en [ErreurApi], jamais en exception brute.
///
/// Les écrans ne connaissent ni dio ni les adresses : ils passent par des
/// dépôts de données qui s'appuient sur cette classe.
final class ClientApi {
  ClientApi({
    required String urlDeBase,
    required DepotDeSession session,
    this.surSessionExpiree,
    Dio? dio,
  }) : _session = session,
       _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = urlDeBase
      ..connectTimeout = const Duration(seconds: 15)
      ..receiveTimeout = const Duration(seconds: 30)
      ..headers['Accept'] = 'application/json';

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final jeton = await _session.lireJeton();
          if (jeton != null) options.headers['Authorization'] = 'Bearer $jeton';
          handler.next(options);
        },
      ),
    );
  }

  final Dio _dio;
  final DepotDeSession _session;

  /// Appelé sur un 401 : l'application renvoie vers l'écran de connexion.
  final void Function()? surSessionExpiree;

  Future<T> lire<T>(String chemin, {Map<String, dynamic>? parametres, T Function(Object? data)? decoder}) =>
      _appeler(() => _dio.get<Object?>(chemin, queryParameters: parametres), decoder);

  /// Envoie une INTENTION. Le serveur recalcule toujours prix, taxes et disponibilité.
  Future<T> envoyer<T>(String chemin, {Object? corps, String methode = 'POST', T Function(Object? data)? decoder}) =>
      _appeler(() => _dio.request<Object?>(chemin, data: corps, options: Options(method: methode)), decoder);

  Future<T> _appeler<T>(Future<Response<Object?>> Function() appel, T Function(Object? data)? decoder) async {
    try {
      final reponse = await appel();
      final corps = reponse.data;
      final data = corps is Map ? corps['data'] : null;
      return decoder != null ? decoder(data) : data as T;
    } on DioException catch (e) {
      final erreur = ErreurApi.depuis(e);
      if (erreur.sessionExpiree) {
        await _session.effacer();
        surSessionExpiree?.call();
      }
      throw erreur;
    }
  }
}
