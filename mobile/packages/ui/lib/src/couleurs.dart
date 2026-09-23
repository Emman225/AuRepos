import 'package:flutter/painting.dart';

/// Charte graphique — mêmes valeurs que web/src/shared/theme/jetons.ts.
/// Une seule palette : Mon Gravier en avait deux, concurrentes.
///
/// Le sable est une SURFACE (cartes, accents), jamais une couleur de texte
/// sur fond clair : son contraste y est insuffisant.
abstract final class Couleurs {
  static const bleuNuit = Color(0xFF1E3A5F); // principale : navigation, boutons, titres
  static const bleuNuitFonce = Color(0xFF152B47);
  static const sable = Color(0xFFD9C3A5); // secondaire : cartes, accents, décor
  static const sableClair = Color(0xFFEFE4D3);
  static const blancCasse = Color(0xFFF8F7F4); // arrière-plans
  static const blanc = Color(0xFFFFFFFF);
  static const texte = Color(0xFF1F2A37);
  static const texteDiscret = Color(0xFF5B6676);
  static const bordure = Color(0xFFE4DED3);
  static const succes = Color(0xFF2E7D5B);
  static const alerte = Color(0xFFB7791F);
  static const erreur = Color(0xFFB3261E);
  static const information = Color(0xFF2B6CB0);
}
