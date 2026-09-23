import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Où vit le jeton de connexion. Une interface, pour que les tests et le
/// client d'API n'aient pas besoin du coffre du téléphone.
abstract interface class DepotDeSession {
  Future<String?> lireJeton();
  Future<void> ecrireJeton(String jeton);
  Future<void> effacer();
}

/// Jeton rangé dans le coffre chiffré du système (Keystore / Keychain).
/// Mon Gravier le gardait en clair dans SharedPreferences.
final class DepotDeSessionSecurise implements DepotDeSession {
  DepotDeSessionSecurise([FlutterSecureStorage? coffre]) : _coffre = coffre ?? const FlutterSecureStorage();

  static const _cle = 'jeton_connexion';
  final FlutterSecureStorage _coffre;

  @override
  Future<String?> lireJeton() => _coffre.read(key: _cle);

  @override
  Future<void> ecrireJeton(String jeton) => _coffre.write(key: _cle, value: jeton);

  @override
  Future<void> effacer() => _coffre.delete(key: _cle);
}

/// Dépôt en mémoire, pour les tests.
final class DepotDeSessionEnMemoire implements DepotDeSession {
  String? _jeton;

  @override
  Future<String?> lireJeton() async => _jeton;

  @override
  Future<void> ecrireJeton(String jeton) async => _jeton = jeton;

  @override
  Future<void> effacer() async => _jeton = null;
}
