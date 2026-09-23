import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_agent/fournisseurs.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';

import 'aide/faux_serveur.dart';

void main() {
  test('sans jeton enregistré, personne n’est connecté', () async {
    final conteneur = ProviderContainer(
      overrides: [depotDeSessionProvider.overrideWithValue(DepotDeSessionEnMemoire())],
    );
    addTearDown(conteneur.dispose);

    expect(await conteneur.read(sessionProvider.future), isNull);
  });

  test('un jeton valide d’un compte agent_terrain restaure l’utilisateur', () async {
    final depot = DepotDeSessionEnMemoire();
    await depot.ecrireJeton('jeton-abc');
    final serveur = FauxServeur(200, {'success': true, 'message': '', 'errors': null, 'data': jsonUtilisateur()});

    final conteneur = ProviderContainer(
      overrides: [
        depotDeSessionProvider.overrideWithValue(depot),
        depotAuthProvider.overrideWithValue(DepotAuth(clientDeTest(serveur, session: depot))),
      ],
    );
    addTearDown(conteneur.dispose);

    final utilisateur = await conteneur.read(sessionProvider.future);

    expect(utilisateur?.nomComplet, 'Awa Kouassi');
    expect(serveur.derniere!.path, '/auth/moi');
  });

  test('un jeton d’un compte d’un autre profil est effacé en silence', () async {
    final depot = DepotDeSessionEnMemoire();
    await depot.ecrireJeton('jeton-client');
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'errors': null,
      'data': jsonUtilisateur(profil: 'client'),
    });

    final conteneur = ProviderContainer(
      overrides: [
        depotDeSessionProvider.overrideWithValue(depot),
        depotAuthProvider.overrideWithValue(DepotAuth(clientDeTest(serveur, session: depot))),
      ],
    );
    addTearDown(conteneur.dispose);

    final utilisateur = await conteneur.read(sessionProvider.future);

    expect(utilisateur, isNull);
    expect(await depot.lireJeton(), isNull);
  });

  test('un jeton périmé (401) est effacé sans faire échouer l’application', () async {
    final depot = DepotDeSessionEnMemoire();
    await depot.ecrireJeton('jeton-perime');
    final serveur = FauxServeur(401, {
      'success': false,
      'message': 'Vous devez vous connecter.',
      'data': null,
      'errors': null,
    });

    final conteneur = ProviderContainer(
      overrides: [
        depotDeSessionProvider.overrideWithValue(depot),
        depotAuthProvider.overrideWithValue(DepotAuth(clientDeTest(serveur, session: depot))),
      ],
    );
    addTearDown(conteneur.dispose);

    final utilisateur = await conteneur.read(sessionProvider.future);

    expect(utilisateur, isNull);
    expect(await depot.lireJeton(), isNull);
  });

  test('definir() place directement l’utilisateur après une connexion réussie', () async {
    final conteneur = ProviderContainer(
      overrides: [depotDeSessionProvider.overrideWithValue(DepotDeSessionEnMemoire())],
    );
    addTearDown(conteneur.dispose);
    await conteneur.read(sessionProvider.future);

    const utilisateur = Utilisateur(
      id: 1,
      nom: 'Kouassi',
      prenoms: 'Awa',
      nomComplet: 'Awa Kouassi',
      email: 'awa@exemple.ci',
      profil: profilAgentTerrain,
    );
    conteneur.read(sessionProvider.notifier).definir(utilisateur);

    expect(conteneur.read(sessionProvider).value?.nomComplet, 'Awa Kouassi');
  });

  test('deconnexion() efface le jeton et remet l’état à null', () async {
    final depot = DepotDeSessionEnMemoire();
    final conteneur = ProviderContainer(overrides: [depotDeSessionProvider.overrideWithValue(depot)]);
    addTearDown(conteneur.dispose);
    await conteneur.read(sessionProvider.future);

    await depot.ecrireJeton('jeton-abc');
    await conteneur.read(sessionProvider.notifier).deconnexion();

    expect(conteneur.read(sessionProvider).value, isNull);
    expect(await depot.lireJeton(), isNull);
  });
}
