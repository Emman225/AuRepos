import 'dart:io';

import 'package:dio/dio.dart';

/// Erreur présentable telle quelle à l'utilisateur : le message est en
/// français clair, qu'il vienne du serveur ou d'une panne de réseau.
final class ErreurApi implements Exception {
  const ErreurApi(this.message, {this.statut = 0, this.champs});

  final String message;

  /// Code HTTP ; 0 quand le serveur n'a pas été atteint.
  final int statut;

  /// Détail de validation par champ (HTTP 422).
  final Map<String, List<String>>? champs;

  bool get sessionExpiree => statut == 401;
  bool get horsLigne => statut == 0;

  /// Typologie des pannes reprise de `messageErreurTechnique()` de Mon Gravier,
  /// mais écrite une seule fois au lieu d'être recopiée dans chaque écran.
  factory ErreurApi.depuis(DioException e) {
    final reponse = e.response;
    if (reponse != null) {
      final corps = reponse.data;
      final message = corps is Map && corps['message'] is String
          ? corps['message'] as String
          : 'La demande ne peut pas aboutir.';

      Map<String, List<String>>? champs;
      if (corps is Map && corps['errors'] is Map) {
        champs = (corps['errors'] as Map).map(
          (cle, valeur) => MapEntry('$cle', [for (final m in valeur as List) '$m']),
        );
      }
      return ErreurApi(message, statut: reponse.statusCode ?? 0, champs: champs);
    }

    return ErreurApi(switch (e.type) {
      DioExceptionType.connectionTimeout ||
      DioExceptionType.sendTimeout ||
      DioExceptionType.receiveTimeout => 'Le serveur met trop de temps à répondre. Réessayez dans un instant.',
      DioExceptionType.badCertificate => 'La connexion sécurisée a échoué. Vérifiez la date et l’heure du téléphone.',
      _ when e.error is SocketException => 'Pas de connexion internet. Vérifiez le réseau puis réessayez.',
      _ => 'Le serveur ne répond pas. Réessayez dans un instant.',
    });
  }

  @override
  String toString() => 'ErreurApi($statut) $message';
}
