import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_ui/residences_ui.dart';

/// Rapport de contraste WCAG entre deux couleurs.
double contraste(Color a, Color b) {
  final la = a.computeLuminance(), lb = b.computeLuminance();
  final clair = la > lb ? la : lb, sombre = la > lb ? lb : la;
  return (clair + 0.05) / (sombre + 0.05);
}

void main() {
  test('le thème porte les trois couleurs de la charte', () {
    final theme = ThemeResidences.clair;

    expect(theme.colorScheme.primary, const Color(0xFF1E3A5F));
    expect(theme.colorScheme.secondary, const Color(0xFFD9C3A5));
    expect(theme.scaffoldBackgroundColor, const Color(0xFFF8F7F4));
  });

  test('le texte bleu nuit reste lisible sur blanc cassé et sur sable (WCAG AA ≥ 4,5)', () {
    expect(contraste(Couleurs.bleuNuit, Couleurs.blancCasse), greaterThanOrEqualTo(4.5));
    expect(contraste(Couleurs.bleuNuit, Couleurs.sable), greaterThanOrEqualTo(4.5));
    expect(contraste(Couleurs.blanc, Couleurs.bleuNuit), greaterThanOrEqualTo(4.5));
  });

  test('le sable n’est pas une couleur de texte sur fond clair', () {
    // Garde-fou de la règle de la charte : ce contraste est volontairement insuffisant.
    expect(contraste(Couleurs.sable, Couleurs.blancCasse), lessThan(3));
  });
}
