import 'vignette_logement.dart';

/// Filtres envoyés à `GET /catalogue/recherche` — mêmes noms de champs que
/// `CriteresRecherche` côté web (`arrivee`/`depart`, pas `date_arrivee`).
final class CriteresRecherche {
  const CriteresRecherche({
    this.arrivee,
    this.depart,
    this.adultes,
    this.enfants,
    this.communeId,
    this.quartierId,
    this.typeLogementId,
    this.budgetMax,
    this.page,
  });

  final String? arrivee;
  final String? depart;
  final int? adultes;
  final int? enfants;
  final int? communeId;
  final int? quartierId;
  final int? typeLogementId;
  final num? budgetMax;
  final int? page;

  Map<String, dynamic> versParametres() => {
    if (arrivee != null && arrivee!.isNotEmpty) 'arrivee': arrivee,
    if (depart != null && depart!.isNotEmpty) 'depart': depart,
    if (adultes != null) 'adultes': adultes,
    if (enfants != null) 'enfants': enfants,
    if (communeId != null) 'commune_id': communeId,
    if (quartierId != null) 'quartier_id': quartierId,
    if (typeLogementId != null) 'type_logement_id': typeLogementId,
    if (budgetMax != null) 'budget_max': budgetMax,
    if (page != null) 'page': page,
  };

  // Égalité par valeur : permet d'utiliser ces critères comme clé d'un
  // FutureProvider.family (recherche déclenchée par l'écran Recherche) et de
  // les comparer directement dans les tests, sans instance partagée.
  @override
  bool operator ==(Object other) =>
      other is CriteresRecherche &&
      other.arrivee == arrivee &&
      other.depart == depart &&
      other.adultes == adultes &&
      other.enfants == enfants &&
      other.communeId == communeId &&
      other.quartierId == quartierId &&
      other.typeLogementId == typeLogementId &&
      other.budgetMax == budgetMax &&
      other.page == page;

  @override
  int get hashCode =>
      Object.hash(arrivee, depart, adultes, enfants, communeId, quartierId, typeLogementId, budgetMax, page);
}

final class Pagination {
  const Pagination({required this.page, required this.parPage, required this.total, required this.dernierePage});

  final int page;
  final int parPage;
  final int total;
  final int dernierePage;

  factory Pagination.depuisJson(Map<String, dynamic> json) => Pagination(
    page: (json['page'] as num?)?.toInt() ?? 1,
    parPage: (json['par_page'] as num?)?.toInt() ?? 0,
    total: (json['total'] as num?)?.toInt() ?? 0,
    dernierePage: (json['derniere_page'] as num?)?.toInt() ?? 1,
  );
}

/// Réponse de `GET /catalogue/recherche`.
final class ResultatsRecherche {
  const ResultatsRecherche({
    required this.avecDates,
    required this.arrivee,
    required this.depart,
    required this.elements,
    required this.pagination,
  });

  final bool avecDates;
  final String arrivee;
  final String depart;
  final List<VignetteLogement> elements;
  final Pagination pagination;

  factory ResultatsRecherche.depuisJson(Map<String, dynamic> json) => ResultatsRecherche(
    avecDates: json['avec_dates'] as bool? ?? false,
    arrivee: json['arrivee'] as String? ?? '',
    depart: json['depart'] as String? ?? '',
    elements: (json['elements'] as List<dynamic>? ?? const [])
        .map((e) => VignetteLogement.depuisJson(e as Map<String, dynamic>))
        .toList(),
    pagination: Pagination.depuisJson((json['pagination'] as Map<String, dynamic>?) ?? const {}),
  );
}
