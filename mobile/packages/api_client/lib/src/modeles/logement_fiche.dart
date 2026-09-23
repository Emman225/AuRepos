import 'lieu.dart';
import 'vignette_logement.dart';

final class PhotoLogement {
  const PhotoLogement({required this.url, required this.urlVignette, this.legende, required this.couverture});

  final String url;
  final String urlVignette;
  final String? legende;
  final bool couverture;

  factory PhotoLogement.depuisJson(Map<String, dynamic> json) => PhotoLogement(
    url: json['url'] as String? ?? '',
    urlVignette: json['url_vignette'] as String? ?? '',
    legende: json['legende'] as String?,
    couverture: json['couverture'] as bool? ?? false,
  );
}

final class Equipement {
  const Equipement({required this.nom, this.icone, required this.portee});

  final String nom;
  final String? icone;
  final String portee;

  factory Equipement.depuisJson(Map<String, dynamic> json) =>
      Equipement(nom: json['nom'] as String? ?? '', icone: json['icone'] as String?, portee: json['portee'] as String? ?? '');
}

final class ReglesLogement {
  const ReglesLogement({
    required this.fumeurAutorise,
    required this.animauxAutorises,
    required this.fetesAutorisees,
    this.texte,
  });

  final bool fumeurAutorise;
  final bool animauxAutorises;
  final bool fetesAutorisees;
  final String? texte;

  factory ReglesLogement.depuisJson(Map<String, dynamic> json) => ReglesLogement(
    fumeurAutorise: json['fumeur_autorise'] as bool? ?? false,
    animauxAutorises: json['animaux_autorises'] as bool? ?? false,
    fetesAutorisees: json['fetes_autorisees'] as bool? ?? false,
    texte: json['texte'] as String?,
  );
}

final class TypeLogementRef {
  const TypeLogementRef({required this.code, required this.nom});

  final String code;
  final String nom;

  factory TypeLogementRef.depuisJson(Map<String, dynamic> json) =>
      TypeLogementRef(code: json['code'] as String? ?? '', nom: json['nom'] as String? ?? '');
}

final class ResidenceRef {
  const ResidenceRef({required this.nom, required this.slug, this.description});

  final String nom;
  final String slug;
  final String? description;

  factory ResidenceRef.depuisJson(Map<String, dynamic> json) => ResidenceRef(
    nom: json['nom'] as String? ?? '',
    slug: json['slug'] as String? ?? '',
    description: json['description'] as String?,
  );
}

/// Fiche complète d'un logement — `GET /catalogue/logements/{reference}`.
/// Le tunnel de réservation (calendrier, devis, paiement) n'est pas construit
/// dans cette tranche ; seuls les champs de consultation sont exploités.
final class LogementFiche {
  const LogementFiche({
    required this.reference,
    required this.nom,
    required this.resume,
    required this.type,
    required this.residence,
    required this.lieu,
    required this.nombrePieces,
    required this.nombreChambres,
    this.nombreLits,
    this.nombreSallesDeBain,
    required this.capaciteDeBase,
    required this.capaciteMaximale,
    this.surfaceM2,
    this.description,
    required this.regles,
    required this.heureArrivee,
    required this.heureDepart,
    required this.prixParNuit,
    required this.devise,
    required this.caution,
    this.dureeMinimale,
    this.dureeMaximale,
    required this.politiqueAnnulationLibelle,
    required this.equipements,
    required this.photos,
    this.noteMoyenne,
    required this.similaires,
  });

  final String reference;
  final String nom;
  final String resume;
  final TypeLogementRef type;
  final ResidenceRef residence;
  final Lieu lieu;
  final int nombrePieces;
  final int nombreChambres;
  final int? nombreLits;
  final int? nombreSallesDeBain;
  final int capaciteDeBase;
  final int capaciteMaximale;
  final num? surfaceM2;
  final String? description;
  final ReglesLogement regles;
  final String heureArrivee;
  final String heureDepart;
  final num prixParNuit;
  final String devise;
  final num caution;
  final int? dureeMinimale;
  final int? dureeMaximale;
  final String politiqueAnnulationLibelle;
  final List<Equipement> equipements;
  final List<PhotoLogement> photos;
  final num? noteMoyenne;
  final List<VignetteLogement> similaires;

  factory LogementFiche.depuisJson(Map<String, dynamic> json) => LogementFiche(
    reference: json['reference'] as String? ?? '',
    nom: json['nom'] as String? ?? '',
    resume: json['resume'] as String? ?? '',
    type: TypeLogementRef.depuisJson((json['type'] as Map<String, dynamic>?) ?? const {}),
    residence: ResidenceRef.depuisJson((json['residence'] as Map<String, dynamic>?) ?? const {}),
    lieu: Lieu.depuisJson((json['lieu'] as Map<String, dynamic>?) ?? const {}),
    nombrePieces: (json['nombre_pieces'] as num?)?.toInt() ?? 0,
    nombreChambres: (json['nombre_chambres'] as num?)?.toInt() ?? 0,
    nombreLits: (json['nombre_lits'] as num?)?.toInt(),
    nombreSallesDeBain: (json['nombre_salles_de_bain'] as num?)?.toInt(),
    capaciteDeBase: (json['capacite_de_base'] as num?)?.toInt() ?? 0,
    capaciteMaximale: (json['capacite_maximale'] as num?)?.toInt() ?? 0,
    surfaceM2: json['surface_m2'] as num?,
    description: json['description'] as String?,
    regles: ReglesLogement.depuisJson((json['regles'] as Map<String, dynamic>?) ?? const {}),
    heureArrivee: json['heure_arrivee'] as String? ?? '',
    heureDepart: json['heure_depart'] as String? ?? '',
    prixParNuit: json['prix_par_nuit'] as num? ?? 0,
    devise: json['devise'] as String? ?? 'XOF',
    caution: json['caution'] as num? ?? 0,
    dureeMinimale: (json['duree_minimale'] as num?)?.toInt(),
    dureeMaximale: (json['duree_maximale'] as num?)?.toInt(),
    politiqueAnnulationLibelle: json['politique_annulation_libelle'] as String? ?? '',
    equipements: (json['equipements'] as List<dynamic>? ?? const [])
        .map((e) => Equipement.depuisJson(e as Map<String, dynamic>))
        .toList(),
    photos: (json['photos'] as List<dynamic>? ?? const [])
        .map((e) => PhotoLogement.depuisJson(e as Map<String, dynamic>))
        .toList(),
    noteMoyenne: json['note_moyenne'] as num?,
    similaires: (json['similaires'] as List<dynamic>? ?? const [])
        .map((e) => VignetteLogement.depuisJson(e as Map<String, dynamic>))
        .toList(),
  );
}
