import 'lieu.dart';

/// Carte résumée d'un logement, utilisée sur l'accueil et dans les résultats
/// de recherche — mêmes champs que `VignetteLogement` côté web
/// (`web/src/features/site-public/types.ts`).
final class VignetteLogement {
  const VignetteLogement({
    required this.reference,
    required this.nom,
    required this.residence,
    required this.resume,
    required this.lieu,
    required this.capaciteMaximale,
    required this.prixParNuit,
    required this.photo,
    required this.noteMoyenne,
  });

  final String reference;
  final String nom;
  final String residence;
  final String resume;
  final Lieu lieu;
  final int capaciteMaximale;
  final num? prixParNuit;
  final String? photo;
  final num? noteMoyenne;

  factory VignetteLogement.depuisJson(Map<String, dynamic> json) => VignetteLogement(
    reference: json['reference'] as String? ?? '',
    nom: json['nom'] as String? ?? '',
    residence: json['residence'] as String? ?? '',
    resume: json['resume'] as String? ?? '',
    lieu: Lieu.depuisJson((json['lieu'] as Map<String, dynamic>?) ?? const {}),
    capaciteMaximale: (json['capacite_maximale'] as num?)?.toInt() ?? 0,
    prixParNuit: json['prix_par_nuit'] as num?,
    photo: json['photo'] as String?,
    noteMoyenne: json['note_moyenne'] as num?,
  );
}
