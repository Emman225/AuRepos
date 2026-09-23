import 'package:flutter/material.dart';

import 'couleurs.dart';

/// Thème unique de toutes les applications.
abstract final class ThemeResidences {
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
