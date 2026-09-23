/// `api/app/Domain/Sejours/Enums/TypeEtatDesLieux.php`.
enum TypeEtatDesLieux {
  entree,
  sortie;

  String get valeur => name;
}

/// `api/app/Http/Resources/Sejours/EtatDesLieuxResource.php`.
final class EtatDesLieux {
  const EtatDesLieux({
    required this.id,
    required this.type,
    required this.typeLibelle,
    this.commentaireGeneral,
    required this.signe,
    this.signeLe,
    this.etabliPar,
    required this.lignes,
  });

  final int id;
  final TypeEtatDesLieux type;
  final String typeLibelle;
  final String? commentaireGeneral;
  final bool signe;
  final String? signeLe;
  final String? etabliPar;
  final List<LigneEtatDesLieux> lignes;

  factory EtatDesLieux.depuisJson(Map<String, dynamic> json) => EtatDesLieux(
    id: (json['id'] as num?)?.toInt() ?? 0,
    type: TypeEtatDesLieux.values.firstWhere(
      (t) => t.valeur == json['type'],
      orElse: () => TypeEtatDesLieux.entree,
    ),
    typeLibelle: json['type_libelle'] as String? ?? '',
    commentaireGeneral: json['commentaire_general'] as String?,
    signe: json['signe'] as bool? ?? false,
    signeLe: json['signe_le'] as String?,
    etabliPar: json['etabli_par'] as String?,
    lignes: (json['lignes'] as List<dynamic>? ?? const [])
        .map((e) => LigneEtatDesLieux.depuisJson(e as Map<String, dynamic>))
        .toList(),
  );
}

/// `api/app/Http/Resources/Sejours/LigneEtatDesLieuxResource.php` — jamais d'URL de photo,
/// seulement son nom d'origine (les photos elles-mêmes sont chiffrées côté serveur).
final class LigneEtatDesLieux {
  const LigneEtatDesLieux({
    required this.id,
    required this.libelle,
    this.observation,
    required this.ordre,
    required this.photos,
  });

  final int id;
  final String libelle;
  final String? observation;
  final int ordre;
  final List<PhotoDeLigne> photos;

  factory LigneEtatDesLieux.depuisJson(Map<String, dynamic> json) => LigneEtatDesLieux(
    id: (json['id'] as num?)?.toInt() ?? 0,
    libelle: json['libelle'] as String? ?? '',
    observation: json['observation'] as String?,
    ordre: (json['ordre'] as num?)?.toInt() ?? 0,
    photos: (json['photos'] as List<dynamic>? ?? const [])
        .map((e) => PhotoDeLigne.depuisJson(e as Map<String, dynamic>))
        .toList(),
  );
}

final class PhotoDeLigne {
  const PhotoDeLigne({required this.id, required this.nomOriginal});

  final int id;
  final String nomOriginal;

  factory PhotoDeLigne.depuisJson(Map<String, dynamic> json) =>
      PhotoDeLigne(id: (json['id'] as num?)?.toInt() ?? 0, nomOriginal: json['nom_original'] as String? ?? '');
}
