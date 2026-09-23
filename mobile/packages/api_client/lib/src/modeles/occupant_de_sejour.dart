/// Fiche de police (CdC § 6.3, § 11) — `api/app/Http/Resources/Sejours/OccupantResource.php`.
/// Jamais le numéro de pièce en clair : seulement `pieceFournie` (fournie ou non) et la liste
/// des photos déposées, comme au back office.
final class OccupantDeSejour {
  const OccupantDeSejour({
    required this.id,
    required this.nom,
    this.prenoms,
    required this.enfant,
    this.typePiece,
    required this.pieceFournie,
    this.telephone,
    required this.pieces,
  });

  final int id;
  final String nom;
  final String? prenoms;
  final bool enfant;
  final String? typePiece;
  final bool pieceFournie;
  final String? telephone;
  final List<PieceOccupant> pieces;

  String get nomComplet => prenoms == null || prenoms!.isEmpty ? nom : '$nom $prenoms';

  factory OccupantDeSejour.depuisJson(Map<String, dynamic> json) => OccupantDeSejour(
    id: (json['id'] as num?)?.toInt() ?? 0,
    nom: json['nom'] as String? ?? '',
    prenoms: json['prenoms'] as String?,
    enfant: json['enfant'] as bool? ?? false,
    typePiece: json['type_piece'] as String?,
    pieceFournie: json['piece_fournie'] as bool? ?? false,
    telephone: json['telephone'] as String?,
    pieces: (json['pieces'] as List<dynamic>? ?? const [])
        .map((e) => PieceOccupant.depuisJson(e as Map<String, dynamic>))
        .toList(),
  );
}

final class PieceOccupant {
  const PieceOccupant({required this.id, required this.statut, required this.nomOriginal});

  final int id;
  final String statut;
  final String nomOriginal;

  factory PieceOccupant.depuisJson(Map<String, dynamic> json) => PieceOccupant(
    id: (json['id'] as num?)?.toInt() ?? 0,
    statut: json['statut'] as String? ?? '',
    nomOriginal: json['nom_original'] as String? ?? '',
  );
}
