import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';

/// Fournisseurs d'infrastructure, remplaçables dans les tests.
/// Aucun état global mutable : Mon Gravier gardait session, panier et taxes
/// dans des variables globales, impossibles à tester ni à réinitialiser.

final depotDeSessionProvider = Provider<DepotDeSession>((ref) => DepotDeSessionSecurise());

final clientApiProvider = Provider<ClientApi>(
  (ref) => ClientApi(
    urlDeBase: Environnement.courant.urlApi,
    session: ref.watch(depotDeSessionProvider),
  ),
);

final depotAuthProvider = Provider<DepotAuth>((ref) => DepotAuth(ref.watch(clientApiProvider)));

final depotCatalogueProvider = Provider<DepotCatalogue>((ref) => DepotCatalogue(ref.watch(clientApiProvider)));

final depotReferentielsProvider = Provider<DepotReferentiels>((ref) => DepotReferentiels(ref.watch(clientApiProvider)));

/// État d'authentification de l'application : `null` = personne connecté.
///
/// Au démarrage, un jeton déjà rangé dans le coffre suffit à retrouver
/// l'utilisateur (`GET /auth/moi`) ; un jeton devenu invalide est effacé en
/// silence plutôt que de bloquer l'application sur une erreur.
///
/// Volontairement minimal : seulement de quoi savoir "connecté ou non" et
/// afficher un nom. Le modèle de session multi-espace du web (agence,
/// peut_encaisser…) n'a pas sa place ici, cette tranche ne le consomme pas.
final sessionProvider = AsyncNotifierProvider<SessionNotifier, Utilisateur?>(SessionNotifier.new);

class SessionNotifier extends AsyncNotifier<Utilisateur?> {
  @override
  FutureOr<Utilisateur?> build() async {
    final jeton = await ref.watch(depotDeSessionProvider).lireJeton();
    if (jeton == null) return null;

    try {
      return await ref.watch(depotAuthProvider).moi();
    } on ErreurApi {
      await ref.watch(depotDeSessionProvider).effacer();
      return null;
    }
  }

  /// Appelé par l'écran de connexion/inscription une fois le jeton rangé.
  void definir(Utilisateur utilisateur) => state = AsyncData(utilisateur);

  Future<void> deconnexion() async {
    await ref.read(depotDeSessionProvider).effacer();
    state = const AsyncData(null);
  }
}
