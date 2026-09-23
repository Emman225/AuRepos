/// Option de commune pour un filtre — `GET /referentiels/communes`.
final class CommuneChoix {
  const CommuneChoix({required this.id, required this.nom});

  final int id;
  final String nom;

  factory CommuneChoix.depuisJson(Map<String, dynamic> json) =>
      CommuneChoix(id: (json['id'] as num?)?.toInt() ?? 0, nom: json['nom'] as String? ?? '');
}
