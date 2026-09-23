import 'lieu.dart';
import 'vignette_logement.dart';

final class Diapositive {
  const Diapositive({required this.id, required this.image, this.legende, this.lien});

  final int id;
  final String image;
  final String? legende;
  final String? lien;

  factory Diapositive.depuisJson(Map<String, dynamic> json) => Diapositive(
    id: (json['id'] as num?)?.toInt() ?? 0,
    image: json['image'] as String? ?? '',
    legende: json['legende'] as String?,
    lien: json['lien'] as String?,
  );
}

final class Banniere {
  const Banniere({required this.id, required this.titre, this.sousTitre, required this.image, this.lien});

  final int id;
  final String titre;
  final String? sousTitre;
  final String image;
  final String? lien;

  factory Banniere.depuisJson(Map<String, dynamic> json) => Banniere(
    id: (json['id'] as num?)?.toInt() ?? 0,
    titre: json['titre'] as String? ?? '',
    sousTitre: json['sous_titre'] as String?,
    image: json['image'] as String? ?? '',
    lien: json['lien'] as String?,
  );
}

final class Temoignage {
  const Temoignage({required this.id, required this.nomClient, required this.message, this.note, this.photo});

  final int id;
  final String nomClient;
  final String message;
  final num? note;
  final String? photo;

  factory Temoignage.depuisJson(Map<String, dynamic> json) => Temoignage(
    id: (json['id'] as num?)?.toInt() ?? 0,
    nomClient: json['nom_client'] as String? ?? '',
    message: json['message'] as String? ?? '',
    note: json['note'] as num?,
    photo: json['photo'] as String?,
  );
}

final class VignetteResidence {
  const VignetteResidence({
    required this.nom,
    required this.slug,
    this.logementReference,
    required this.lieu,
    this.photo,
    this.aPartirDe,
    required this.nombreLogements,
    this.noteMoyenne,
  });

  final String nom;
  final String slug;
  final String? logementReference;
  final Lieu lieu;
  final String? photo;
  final num? aPartirDe;
  final int nombreLogements;
  final num? noteMoyenne;

  factory VignetteResidence.depuisJson(Map<String, dynamic> json) => VignetteResidence(
    nom: json['nom'] as String? ?? '',
    slug: json['slug'] as String? ?? '',
    logementReference: json['logement_reference'] as String?,
    lieu: Lieu.depuisJson((json['lieu'] as Map<String, dynamic>?) ?? const {}),
    photo: json['photo'] as String?,
    aPartirDe: json['a_partir_de'] as num?,
    nombreLogements: (json['nombre_logements'] as num?)?.toInt() ?? 0,
    noteMoyenne: json['note_moyenne'] as num?,
  );
}

/// Réponse de `GET /accueil` — voir `web/src/features/site-public/types.ts` (`DonneesAccueil`).
final class DonneesAccueil {
  const DonneesAccueil({
    required this.carrousel,
    required this.misesEnAvant,
    required this.residencesMisesEnAvant,
    required this.residencesMieuxNotees,
    required this.bannieres,
    required this.temoignages,
  });

  final List<Diapositive> carrousel;
  final List<VignetteLogement> misesEnAvant;
  final List<VignetteResidence> residencesMisesEnAvant;
  final List<VignetteResidence> residencesMieuxNotees;
  final List<Banniere> bannieres;
  final List<Temoignage> temoignages;

  factory DonneesAccueil.depuisJson(Map<String, dynamic> json) => DonneesAccueil(
    carrousel: _liste(json['carrousel'], Diapositive.depuisJson),
    misesEnAvant: _liste(json['mises_en_avant'], VignetteLogement.depuisJson),
    residencesMisesEnAvant: _liste(json['residences_mises_en_avant'], VignetteResidence.depuisJson),
    residencesMieuxNotees: _liste(json['residences_mieux_notees'], VignetteResidence.depuisJson),
    bannieres: _liste(json['bannieres'], Banniere.depuisJson),
    temoignages: _liste(json['temoignages'], Temoignage.depuisJson),
  );
}

List<T> _liste<T>(Object? source, T Function(Map<String, dynamic>) depuisJson) =>
    (source as List<dynamic>?)?.map((e) => depuisJson(e as Map<String, dynamic>)).toList() ?? const [];
