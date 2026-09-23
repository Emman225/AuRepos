import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';

import 'services/selecteur_de_photo.dart';

/// Fournisseurs d'infrastructure, remplaçables dans les tests — même patron que
/// `apps/client/lib/fournisseurs.dart` : aucun état global mutable.

/// Seul profil admis dans cette application (`api/app/Domain/Comptes/Enums/Profil.php`).
/// Un compte de tout autre profil se voit refuser l'accès, voir [SessionNotifier].
const profilAgentTerrain = 'agent_terrain';

final depotDeSessionProvider = Provider<DepotDeSession>((ref) => DepotDeSessionSecurise());

final clientApiProvider = Provider<ClientApi>(
  (ref) => ClientApi(
    urlDeBase: Environnement.courant.urlApi,
    session: ref.watch(depotDeSessionProvider),
  ),
);

final depotAuthProvider = Provider<DepotAuth>((ref) => DepotAuth(ref.watch(clientApiProvider)));

final depotAgentProvider = Provider<DepotAgent>((ref) => DepotAgent(ref.watch(clientApiProvider)));

final selecteurDePhotoProvider = Provider<SelecteurDePhoto>((ref) => SelecteurDePhotoSysteme());

/// État d'authentification : `null` = personne connecté (ou profil refusé).
///
/// Contrairement à l'application Client, cette application est réservée aux comptes
/// `agent_terrain` (CdC § 6.3) : un jeton restauré au démarrage qui appartient à un autre
/// profil est effacé en silence, exactement comme un jeton devenu invalide. Le refus
/// explicite avec message (« ce compte n'est pas un compte agent de terrain ») a lieu au
/// moment de la connexion elle-même, voir `ConnexionEcran`.
final sessionProvider = AsyncNotifierProvider<SessionNotifier, Utilisateur?>(SessionNotifier.new);

class SessionNotifier extends AsyncNotifier<Utilisateur?> {
  @override
  FutureOr<Utilisateur?> build() async {
    final jeton = await ref.watch(depotDeSessionProvider).lireJeton();
    if (jeton == null) return null;

    try {
      final utilisateur = await ref.watch(depotAuthProvider).moi();
      if (utilisateur.profil != profilAgentTerrain) {
        await ref.watch(depotDeSessionProvider).effacer();
        return null;
      }
      return utilisateur;
    } on ErreurApi {
      await ref.watch(depotDeSessionProvider).effacer();
      return null;
    }
  }

  /// Appelé par l'écran de connexion une fois le jeton rangé et le profil vérifié.
  void definir(Utilisateur utilisateur) => state = AsyncData(utilisateur);

  Future<void> deconnexion() async {
    await ref.read(depotDeSessionProvider).effacer();
    state = const AsyncData(null);
  }
}
