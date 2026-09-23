/// Vue agent de terrain d'un séjour — mêmes champs que
/// `api/app/Http/Resources/Backoffice/SejourResource.php` (l'espace agent réutilise
/// exactement la ressource back office, voir `routes/api_v1/agent.php`). Jamais le code
/// d'arrivée (CdC § 11) : seulement `codeDArriveeEmis`, un indicateur.
///
/// Le devis figé (`devis` côté back office) n'est pas repris ici : aucun écran agent ne
/// l'affiche (ni web ni cette tranche), inutile de le décoder.
final class SejourAgent {
  const SejourAgent({
    required this.id,
    required this.reference,
    required this.etat,
    required this.etatLibelle,
    required this.client,
    required this.logement,
    required this.arrivee,
    required this.depart,
    required this.nombreDeNuits,
    required this.adultes,
    required this.enfants,
    required this.netAPayer,
    required this.caution,
    required this.reglement,
    required this.arriveLe,
    required this.partiLe,
    required this.noShowLe,
    required this.cautionRetenue,
    required this.cautionRetenueMotif,
    required this.codeDArriveeEmis,
  });

  final int id;
  final String reference;
  final String etat;
  final String etatLibelle;
  final ClientDeSejour? client;
  final LogementDeSejour logement;

  /// Dates brutes `aaaa-mm-jj`, à mettre en forme avec `Formats.date` côté écran.
  final String arrivee;
  final String depart;
  final int nombreDeNuits;
  final int adultes;
  final int enfants;
  final num netAPayer;
  final num caution;
  final SoldeDuSejour reglement;

  /// Déjà mis en forme par le serveur (`jj/mm/aaaa hh:mm:ss`) : affiché tel quel.
  final String? arriveLe;
  final String? partiLe;
  final String? noShowLe;
  final num? cautionRetenue;
  final String? cautionRetenueMotif;
  final bool codeDArriveeEmis;

  bool get estConfirme => etat == 'confirme';
  bool get estArrive => etat == 'arrive';

  factory SejourAgent.depuisJson(Map<String, dynamic> json) => SejourAgent(
    id: (json['id'] as num?)?.toInt() ?? 0,
    reference: json['reference'] as String? ?? '',
    etat: json['etat'] as String? ?? '',
    etatLibelle: json['etat_libelle'] as String? ?? '',
    client: json['client'] != null ? ClientDeSejour.depuisJson(json['client'] as Map<String, dynamic>) : null,
    logement: LogementDeSejour.depuisJson((json['logement'] as Map<String, dynamic>?) ?? const {}),
    arrivee: json['arrivee'] as String? ?? '',
    depart: json['depart'] as String? ?? '',
    nombreDeNuits: (json['nombre_de_nuits'] as num?)?.toInt() ?? 0,
    adultes: (json['adultes'] as num?)?.toInt() ?? 0,
    enfants: (json['enfants'] as num?)?.toInt() ?? 0,
    netAPayer: (json['net_a_payer'] as num?) ?? 0,
    caution: (json['caution'] as num?) ?? 0,
    reglement: SoldeDuSejour.depuisJson((json['reglement'] as Map<String, dynamic>?) ?? const {}),
    arriveLe: json['arrive_le'] as String?,
    partiLe: json['parti_le'] as String?,
    noShowLe: json['no_show_le'] as String?,
    cautionRetenue: json['caution_retenue'] as num?,
    cautionRetenueMotif: json['caution_retenue_motif'] as String?,
    codeDArriveeEmis: json['code_d_arrivee_emis'] as bool? ?? false,
  );
}

final class ClientDeSejour {
  const ClientDeSejour({required this.id, required this.nom, required this.email, this.telephone});

  final int id;
  final String nom;
  final String email;
  final String? telephone;

  factory ClientDeSejour.depuisJson(Map<String, dynamic> json) => ClientDeSejour(
    id: (json['id'] as num?)?.toInt() ?? 0,
    nom: json['nom'] as String? ?? '',
    email: json['email'] as String? ?? '',
    telephone: json['telephone'] as String?,
  );
}

final class LogementDeSejour {
  const LogementDeSejour({required this.id, required this.reference, required this.nom, required this.residence});

  final int id;
  final String reference;
  final String nom;
  final String residence;

  factory LogementDeSejour.depuisJson(Map<String, dynamic> json) => LogementDeSejour(
    id: (json['id'] as num?)?.toInt() ?? 0,
    reference: json['reference'] as String? ?? '',
    nom: json['nom'] as String? ?? '',
    residence: json['residence'] as String? ?? '',
  );
}

/// `api/app/Domain/Caisse/Services/SoldeDesSejours.php::de()`.
final class SoldeDuSejour {
  const SoldeDuSejour({required this.encaisse, required this.resteDu});

  final num encaisse;
  final num resteDu;

  factory SoldeDuSejour.depuisJson(Map<String, dynamic> json) =>
      SoldeDuSejour(encaisse: (json['encaisse'] as num?) ?? 0, resteDu: (json['reste_du'] as num?) ?? 0);
}
