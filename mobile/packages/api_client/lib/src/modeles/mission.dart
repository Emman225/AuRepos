/// `api/app/Domain/Exploitation/Enums/StatutDeMission.php`.
enum StatutDeMission {
  aFaire,
  enCours,
  faite;

  String get valeur => switch (this) {
    StatutDeMission.aFaire => 'a_faire',
    StatutDeMission.enCours => 'en_cours',
    StatutDeMission.faite => 'faite',
  };

  static StatutDeMission depuisValeur(String? valeur) => switch (valeur) {
    'en_cours' => StatutDeMission.enCours,
    'faite' => StatutDeMission.faite,
    _ => StatutDeMission.aFaire,
  };
}

/// `api/app/Http/Resources/Exploitation/MissionResource.php`.
final class Mission {
  const Mission({
    required this.id,
    required this.type,
    required this.typeLibelle,
    required this.origineLibelle,
    required this.statut,
    required this.statutLibelle,
    this.logement,
    this.sejour,
    this.agent,
    required this.echeance,
    this.notes,
    this.debuteeLe,
    this.termineeLe,
  });

  final int id;
  final String type;
  final String typeLibelle;
  final String origineLibelle;
  final StatutDeMission statut;
  final String statutLibelle;
  final LogementDeMission? logement;
  final SejourDeMission? sejour;

  /// Nom complet de l'agent affecté ; jamais son identifiant (`MissionResource` ne l'expose pas).
  final String? agent;

  /// `aaaa-mm-jj hh:mm:ss`, à mettre en forme côté écran.
  final String echeance;
  final String? notes;

  /// Déjà mis en forme par le serveur (`jj/mm/aaaa hh:mm:ss`).
  final String? debuteeLe;
  final String? termineeLe;

  factory Mission.depuisJson(Map<String, dynamic> json) => Mission(
    id: (json['id'] as num?)?.toInt() ?? 0,
    type: json['type'] as String? ?? '',
    typeLibelle: json['type_libelle'] as String? ?? '',
    origineLibelle: json['origine_libelle'] as String? ?? '',
    statut: StatutDeMission.depuisValeur(json['statut'] as String?),
    statutLibelle: json['statut_libelle'] as String? ?? '',
    logement: json['logement'] != null ? LogementDeMission.depuisJson(json['logement'] as Map<String, dynamic>) : null,
    sejour: json['sejour'] != null ? SejourDeMission.depuisJson(json['sejour'] as Map<String, dynamic>) : null,
    agent: json['agent'] as String?,
    echeance: json['echeance'] as String? ?? '',
    notes: json['notes'] as String?,
    debuteeLe: json['debutee_le'] as String?,
    termineeLe: json['terminee_le'] as String?,
  );
}

final class LogementDeMission {
  const LogementDeMission({required this.id, required this.nom, this.residence});

  final int id;
  final String nom;
  final String? residence;

  factory LogementDeMission.depuisJson(Map<String, dynamic> json) => LogementDeMission(
    id: (json['id'] as num?)?.toInt() ?? 0,
    nom: json['nom'] as String? ?? '',
    residence: json['residence'] as String?,
  );
}

final class SejourDeMission {
  const SejourDeMission({required this.reference, required this.arrivee, required this.depart});

  final String reference;
  final String arrivee;
  final String depart;

  factory SejourDeMission.depuisJson(Map<String, dynamic> json) => SejourDeMission(
    reference: json['reference'] as String? ?? '',
    arrivee: json['arrivee'] as String? ?? '',
    depart: json['depart'] as String? ?? '',
  );
}
