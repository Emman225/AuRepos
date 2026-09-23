import '../client_api.dart';
import '../modeles/session_ouverte.dart';
import '../modeles/utilisateur.dart';

/// Résultat de `POST /auth/inscription` : le compte est créé mais en attente
/// de vérification par code — pas de jeton tant que le code n'est pas saisi.
final class InscriptionEnAttente {
  const InscriptionEnAttente({required this.email, required this.codeValableMinutes});

  final String email;
  final int codeValableMinutes;

  factory InscriptionEnAttente.depuisJson(Map<String, dynamic> json) => InscriptionEnAttente(
    email: json['email'] as String? ?? '',
    codeValableMinutes: (json['code_valable_minutes'] as num?)?.toInt() ?? 0,
  );
}

/// Authentification — mêmes routes que `web/src/features/auth/api.ts`.
///
/// La vérification par code, le mot de passe oublié et sa réinitialisation
/// ne sont pas exposés ici : hors périmètre de cette tranche (inscription
/// simple + connexion seulement).
final class DepotAuth {
  DepotAuth(this._api);

  final ClientApi _api;

  Future<SessionOuverte> connexion({required String identifiant, required String motDePasse}) => _api.envoyer(
    '/auth/connexion',
    corps: {'identifiant': identifiant, 'mot_de_passe': motDePasse},
    decoder: (data) => SessionOuverte.depuisJson(data! as Map<String, dynamic>),
  );

  Future<InscriptionEnAttente> inscription({
    required String nom,
    required String prenoms,
    required String email,
    String? telephone,
    required String motDePasse,
    required String motDePasseConfirmation,
    required bool conditionsAcceptees,
  }) => _api.envoyer(
    '/auth/inscription',
    corps: {
      'nom': nom,
      'prenoms': prenoms,
      'email': email,
      if (telephone != null && telephone.isNotEmpty) 'telephone': telephone,
      'mot_de_passe': motDePasse,
      'mot_de_passe_confirmation': motDePasseConfirmation,
      'conditions_acceptees': conditionsAcceptees,
    },
    decoder: (data) => InscriptionEnAttente.depuisJson(data! as Map<String, dynamic>),
  );

  Future<Utilisateur> moi() =>
      _api.lire('/auth/moi', decoder: (data) => Utilisateur.depuisJson(data! as Map<String, dynamic>));

  Future<void> deconnexion() => _api.envoyer<void>('/auth/deconnexion');
}
