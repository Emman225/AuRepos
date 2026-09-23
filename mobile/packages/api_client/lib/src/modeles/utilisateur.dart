/// Utilisateur connecté — sous-ensemble du `Utilisateur` web
/// (`web/src/features/auth/session.ts`) : cette tranche n'a besoin que de
/// quoi afficher un nom et distinguer un profil, pas du modèle multi-espace complet.
final class Utilisateur {
  const Utilisateur({
    required this.id,
    required this.nom,
    this.prenoms,
    required this.nomComplet,
    required this.email,
    this.telephone,
    required this.profil,
  });

  final int id;
  final String nom;
  final String? prenoms;
  final String nomComplet;
  final String email;
  final String? telephone;
  final String profil;

  factory Utilisateur.depuisJson(Map<String, dynamic> json) => Utilisateur(
    id: (json['id'] as num?)?.toInt() ?? 0,
    nom: json['nom'] as String? ?? '',
    prenoms: json['prenoms'] as String?,
    nomComplet: json['nom_complet'] as String? ?? (json['nom'] as String? ?? ''),
    email: json['email'] as String? ?? '',
    telephone: json['telephone'] as String?,
    profil: json['profil'] as String? ?? '',
  );
}
