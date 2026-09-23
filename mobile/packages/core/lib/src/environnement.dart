/// Cible de l'application, choisie À LA COMPILATION :
///
///   flutter run --dart-define=ENV=dev
///   flutter build apk --dart-define=ENV=prod --dart-define=API_URL=https://api.exemple.ci/api/v1
///
/// Mon Gravier basculait en éditant `const env = 'prod'` dans le code, avec
/// des adresses IP de postes de développeurs livrées en production.
enum Environnement {
  dev,
  recette,
  prod;

  static Environnement get courant => switch (const String.fromEnvironment('ENV', defaultValue: 'dev')) {
    'prod' => Environnement.prod,
    'recette' => Environnement.recette,
    _ => Environnement.dev,
  };

  /// Adresse de l'API. `API_URL` l'emporte toujours ; sinon une valeur par
  /// défaut existe pour le développement seulement (10.0.2.2 = le poste vu
  /// depuis l'émulateur Android).
  String get urlApi {
    const forcee = String.fromEnvironment('API_URL');
    if (forcee.isNotEmpty) return forcee;

    return switch (this) {
      Environnement.dev => 'http://10.0.2.2:8000/api/v1',
      _ => throw StateError("API_URL est obligatoire hors développement : --dart-define=API_URL=…"),
    };
  }
}
