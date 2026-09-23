import 'package:flutter/material.dart';

import 'couleurs.dart';

/// Thème unique de toutes les applications.
abstract final class ThemeResidences {
  /// À passer en `style` à tout `FilledButton` posé dans un contexte HORIZONTAL — une `Row`,
  /// les `actions` d'un `AlertDialog` (un `OverflowBar`), un `Wrap`.
  ///
  /// Le thème donne aux `FilledButton` un `minimumSize: Size.fromHeight(48)`, c'est-à-dire une
  /// largeur MINIMALE infinie : voulu, les actions principales occupent toute la largeur de leur
  /// colonne. Mais un parent horizontal mesure ses enfants sans borne de largeur, et cette
  /// largeur minimale infinie devient alors une contrainte impossible — l'écran plante en
  /// « BoxConstraints forces an infinite width », pas seulement en test.
  ///
  /// Ce style ne retire que le plancher de largeur : la hauteur de 48 et l'apparence restent
  /// celles du thème.
  static ButtonStyle get boutonEnLigne => FilledButton.styleFrom(minimumSize: const Size(0, 48));

  static ThemeData get clair {
    const schema = ColorScheme(
      brightness: Brightness.light,
      primary: Couleurs.bleuNuit,
      onPrimary: Couleurs.blanc,
      secondary: Couleurs.sable,
      onSecondary: Couleurs.bleuNuit,
      surface: Couleurs.blanc,
      onSurface: Couleurs.texte,
      surfaceContainerHighest: Couleurs.sableClair,
      outline: Couleurs.bordure,
      error: Couleurs.erreur,
      onError: Couleurs.blanc,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: schema,
      scaffoldBackgroundColor: Couleurs.blancCasse,
      appBarTheme: const AppBarTheme(
        backgroundColor: Couleurs.bleuNuit,
        foregroundColor: Couleurs.blanc,
        centerTitle: false,
        elevation: 0,
      ),
      cardTheme: CardThemeData(
        color: Couleurs.blanc,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Couleurs.bordure),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: Couleurs.bleuNuit,
          foregroundColor: Couleurs.blanc,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Couleurs.blanc,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: Couleurs.bordure),
        ),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: Couleurs.blanc,
        indicatorColor: Couleurs.sable,
      ),
      textTheme: const TextTheme(
        headlineMedium: TextStyle(color: Couleurs.bleuNuit, fontWeight: FontWeight.w700),
        titleLarge: TextStyle(color: Couleurs.bleuNuit, fontWeight: FontWeight.w600),
        bodyMedium: TextStyle(color: Couleurs.texte),
      ),
    );
  }
}
