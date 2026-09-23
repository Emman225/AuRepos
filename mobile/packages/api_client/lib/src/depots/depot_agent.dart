import 'package:dio/dio.dart';

import '../client_api.dart';
import '../modeles/consommations_sejour.dart';
import '../modeles/etat_des_lieux.dart';
import '../modeles/mission.dart';
import '../modeles/occupant_de_sejour.dart';
import '../modeles/sejour_agent.dart';

/// Espace agent de terrain (self-service, CdC § 6.3, P2-MOB-04) — mêmes routes que
/// `web/src/features/agent-terrain/api.ts` (`routes/api_v1/agent.php`) : mes séjours du jour,
/// check-in/check-out, fiche de police, état des lieux, mes missions de ménage.
///
/// Aucune donnée de rémunération/gains n'est exposée ici : contrairement au chauffeur ou au
/// livreur (des partenaires, `Domain/Partenaires`), l'agent de terrain n'est qu'un compte
/// `User` de profil `agent_terrain` — aucune route `/agent/gains` n'existe côté API à ce jour.
final class DepotAgent {
  DepotAgent(this._api);

  final ClientApi _api;

  // --------------------------------------------------------- Mes séjours du jour (P2-MOB-04)

  /// `GET /agent/sejours` : arrivées confirmées dues + départs arrivés dus, ma journée.
  Future<List<SejourAgent>> mesSejoursDuJour() => _api.lire(
    '/agent/sejours',
    decoder: (data) => (data! as List<dynamic>).map((e) => SejourAgent.depuisJson(e as Map<String, dynamic>)).toList(),
  );

  Future<SejourAgent> afficherUnSejour(int id) =>
      _api.lire('/agent/sejours/$id', decoder: (data) => SejourAgent.depuisJson(data! as Map<String, dynamic>));

  Future<List<OccupantDeSejour>> listerLesOccupants(int id) => _api.lire(
    '/agent/sejours/$id/occupants',
    decoder: (data) => (data! as List<dynamic>).map((e) => OccupantDeSejour.depuisJson(e as Map<String, dynamic>)).toList(),
  );

  /// Photo de la pièce d'identité d'un occupant (fiche de police, P2-SEJ-01).
  Future<OccupantDeSejour> televerserLaPieceDUnOccupant(int id, int occupantId, {required String cheminFichier}) async {
    final formulaire = FormData.fromMap({'fichier': await MultipartFile.fromFile(cheminFichier)});
    return _api.envoyer(
      '/agent/sejours/$id/occupants/$occupantId/piece',
      corps: formulaire,
      decoder: (data) => OccupantDeSejour.depuisJson(data! as Map<String, dynamic>),
    );
  }

  /// `POST .../check-in` : `code` = code d'arrivée SAISI par l'agent, jamais affiché avant (CdC § 11).
  Future<SejourAgent> faireLeCheckIn(int id, String code) => _api.envoyer(
    '/agent/sejours/$id/check-in',
    corps: {'code': code},
    decoder: (data) => SejourAgent.depuisJson(data! as Map<String, dynamic>),
  );

  Future<ConsommationsDuSejour> afficherLesConsommations(int id) => _api.lire(
    '/agent/sejours/$id/consommations',
    decoder: (data) => ConsommationsDuSejour.depuisJson(data! as Map<String, dynamic>),
  );

  /// `motif` reste facultatif côté API mais l'écran l'exige dès qu'une caution est retenue.
  Future<SejourAgent> faireLeCheckOut(int id, int cautionRetenue, {String? motif}) => _api.envoyer(
    '/agent/sejours/$id/check-out',
    corps: {'caution_retenue': cautionRetenue, if (motif != null && motif.isNotEmpty) 'motif': motif},
    decoder: (data) => SejourAgent.depuisJson(data! as Map<String, dynamic>),
  );

  // --------------------------------------------------------- État des lieux (P2-SEJ-02)

  Future<List<EtatDesLieux>> listerLesEtatsDesLieux(int id) => _api.lire(
    '/agent/sejours/$id/etats-des-lieux',
    decoder: (data) => (data! as List<dynamic>).map((e) => EtatDesLieux.depuisJson(e as Map<String, dynamic>)).toList(),
  );

  Future<EtatDesLieux> etablirUnEtatDesLieux(int id, TypeEtatDesLieux type, {String? commentaireGeneral}) => _api.envoyer(
    '/agent/sejours/$id/etats-des-lieux',
    corps: {'type': type.valeur, if (commentaireGeneral != null && commentaireGeneral.isNotEmpty) 'commentaire_general': commentaireGeneral},
    decoder: (data) => EtatDesLieux.depuisJson(data! as Map<String, dynamic>),
  );

  Future<LigneEtatDesLieux> ajouterUneLigneEtatDesLieux(int id, int etatDesLieuId, String libelle, {String? observation}) => _api.envoyer(
    '/agent/sejours/$id/etats-des-lieux/$etatDesLieuId/lignes',
    corps: {'libelle': libelle, if (observation != null && observation.isNotEmpty) 'observation': observation},
    decoder: (data) => LigneEtatDesLieux.depuisJson(data! as Map<String, dynamic>),
  );

  Future<LigneEtatDesLieux> ajouterUnePhotoDeLigne(int id, int etatDesLieuId, int ligneId, {required String cheminFichier}) async {
    final formulaire = FormData.fromMap({'fichier': await MultipartFile.fromFile(cheminFichier)});
    return _api.envoyer(
      '/agent/sejours/$id/etats-des-lieux/$etatDesLieuId/lignes/$ligneId/photos',
      corps: formulaire,
      decoder: (data) => LigneEtatDesLieux.depuisJson(data! as Map<String, dynamic>),
    );
  }

  /// `signatureBase64` : image signée à l'écran, encodée en base64 — verrouille l'état des lieux.
  Future<EtatDesLieux> signerUnEtatDesLieux(int id, int etatDesLieuId, String signatureBase64) => _api.envoyer(
    '/agent/sejours/$id/etats-des-lieux/$etatDesLieuId/signature',
    corps: {'signature': signatureBase64},
    decoder: (data) => EtatDesLieux.depuisJson(data! as Map<String, dynamic>),
  );

  // --------------------------------------------------------- Mes missions de ménage (P2-MEN-01)

  /// `GET /agent/missions` : seulement celles qui me sont affectées.
  Future<List<Mission>> mesMissions({StatutDeMission? statut}) => _api.lire(
    '/agent/missions',
    parametres: statut != null ? {'statut': statut.valeur} : null,
    decoder: (data) => (data! as List<dynamic>).map((e) => Mission.depuisJson(e as Map<String, dynamic>)).toList(),
  );

  Future<Mission> demarrerUneMission(int id) =>
      _api.envoyer('/agent/missions/$id/debut', decoder: (data) => Mission.depuisJson(data! as Map<String, dynamic>));

  /// Pas encore de check-list/photos/anomalie ici : `Missions::terminer()` ne prend
  /// aujourd'hui qu'un commentaire libre (`{notes?}`) — voir le rapport de livraison.
  Future<Mission> terminerUneMission(int id, {String? notes}) => _api.envoyer(
    '/agent/missions/$id/fin',
    corps: {if (notes != null && notes.isNotEmpty) 'notes': notes},
    decoder: (data) => Mission.depuisJson(data! as Map<String, dynamic>),
  );
}
