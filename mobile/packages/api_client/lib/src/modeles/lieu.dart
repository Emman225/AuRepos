/// Localisation d'un logement ou d'une résidence : commune puis quartier.
final class Lieu {
  const Lieu({required this.commune, required this.quartier});

  final String commune;
  final String quartier;

  factory Lieu.depuisJson(Map<String, dynamic> json) =>
      Lieu(commune: json['commune'] as String? ?? '', quartier: json['quartier'] as String? ?? '');
}
