/// `api/app/Domain/Sejours/Services/CheckOut.php::consommations()` — un seul objet
/// récapitulatif, pas une liste de lignes.
final class ConsommationsDuSejour {
  const ConsommationsDuSejour({
    required this.hebergement,
    required this.repas,
    required this.transferts,
    required this.total,
  });

  final num hebergement;
  final num repas;
  final num transferts;
  final num total;

  factory ConsommationsDuSejour.depuisJson(Map<String, dynamic> json) => ConsommationsDuSejour(
    hebergement: (json['hebergement'] as num?) ?? 0,
    repas: (json['repas'] as num?) ?? 0,
    transferts: (json['transferts'] as num?) ?? 0,
    total: (json['total'] as num?) ?? 0,
  );
}
