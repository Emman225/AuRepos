import 'utilisateur.dart';

/// Réponse de `POST /auth/connexion` (et `/auth/verification`, `/auth/rafraichir`).
final class SessionOuverte {
  const SessionOuverte({required this.jeton, required this.expireDans, required this.utilisateur});

  final String jeton;
  final int expireDans;
  final Utilisateur utilisateur;

  factory SessionOuverte.depuisJson(Map<String, dynamic> json) => SessionOuverte(
    jeton: json['jeton'] as String? ?? '',
    expireDans: (json['expire_dans'] as num?)?.toInt() ?? 0,
    utilisateur: Utilisateur.depuisJson((json['utilisateur'] as Map<String, dynamic>?) ?? const {}),
  );
}
