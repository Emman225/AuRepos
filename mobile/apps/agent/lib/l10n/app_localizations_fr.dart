// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for French (`fr`).
class LibellesFr extends Libelles {
  LibellesFr([String locale = 'fr']) : super(locale);

  @override
  String get marque => 'Agent de terrain';

  @override
  String get reessayer => 'Réessayer';

  @override
  String get erreurGenerique =>
      'Une erreur est survenue. Réessayez dans un instant.';

  @override
  String get champObligatoire => 'Ce champ est obligatoire.';

  @override
  String get annuler => 'Annuler';

  @override
  String get valider => 'Valider';

  @override
  String get deconnexion => 'Déconnexion';

  @override
  String get connexionTitre => 'Connexion agent';

  @override
  String get champIdentifiant => 'Email ou téléphone';

  @override
  String get champMotDePasse => 'Mot de passe';

  @override
  String get boutonSeConnecter => 'Se connecter';

  @override
  String get erreurProfilInvalide =>
      'Ce compte n’est pas un compte agent de terrain.';

  @override
  String bienvenue(String prenom) {
    return 'Bonjour, $prenom';
  }

  @override
  String get kpiArrivees => 'Arrivées à accueillir';

  @override
  String get kpiDeparts => 'Départs à faire sortir';

  @override
  String get kpiMissions => 'Missions à faire';

  @override
  String get menuSejours => 'Mes séjours';

  @override
  String get menuMissions => 'Mes missions';

  @override
  String get menuGains => 'Mes gains';

  @override
  String get sejoursTitre => 'Mes séjours du jour';

  @override
  String get sejoursAucun => 'Aucun séjour aujourd’hui.';

  @override
  String get sejoursClient => 'Client';

  @override
  String get sejoursLogement => 'Logement';

  @override
  String get sejoursArrivee => 'Arrivée';

  @override
  String get sejoursDepart => 'Départ';

  @override
  String get sejourIntrouvable => 'Ce séjour n’est pas disponible.';

  @override
  String get detailRetour => '← Retour à la liste';

  @override
  String get detailNetAPayer => 'Net à payer';

  @override
  String get detailSejour => 'Séjour';

  @override
  String detailNuits(int count) {
    return '$count nuit(s)';
  }

  @override
  String get detailOccupants => 'Occupants';

  @override
  String detailOccupantsAdultes(int count) {
    return '$count adulte(s)';
  }

  @override
  String detailEtEnfants(int count) {
    return ', $count enfant(s)';
  }

  @override
  String get detailClient => 'Client';

  @override
  String get detailPaiement => 'Paiement';

  @override
  String get detailEncaisse => 'Encaissé';

  @override
  String get detailResteDu => 'Reste dû';

  @override
  String get detailCaution => 'Caution';

  @override
  String get detailCautionRetenue => 'Caution retenue';

  @override
  String detailNoShow(String date) {
    return 'Signalé absent le $date';
  }

  @override
  String detailCautionRetenueInfo(String montant) {
    return 'Caution retenue : $montant';
  }

  @override
  String get checkInAction => 'Faire le check-in';

  @override
  String get checkInTitre => 'Check-in';

  @override
  String get checkInAide =>
      'Le client vous communique son code d’arrivée : saisissez-le ci-dessous.';

  @override
  String get checkInCode => 'Code d’arrivée';

  @override
  String get checkInConfirmer => 'Confirmer le check-in';

  @override
  String get checkOutAction => 'Faire le check-out';

  @override
  String get checkOutTitre => 'Check-out';

  @override
  String get checkOutConsommations => 'Consommations facturées';

  @override
  String get checkOutHebergement => 'Hébergement';

  @override
  String get checkOutRepas => 'Repas';

  @override
  String get checkOutTransferts => 'Transferts';

  @override
  String get checkOutTotal => 'Total';

  @override
  String get checkOutAucuneConsommation => 'Aucune consommation à facturer.';

  @override
  String get checkOutCautionRetenue => 'Caution retenue';

  @override
  String get checkOutMotif => 'Motif';

  @override
  String get checkOutMotifObligatoire =>
      'Un motif est obligatoire dès qu’une caution est retenue.';

  @override
  String get checkOutConfirmer => 'Confirmer le check-out';

  @override
  String get occupantsTitre => 'Occupants — fiche de police';

  @override
  String get occupantsNom => 'Nom';

  @override
  String get occupantsEnfant => 'enfant';

  @override
  String get occupantsTypePiece => 'Type de pièce';

  @override
  String get occupantsTelephone => 'Téléphone';

  @override
  String get occupantsPiece => 'Pièce d’identité';

  @override
  String get occupantsAucunePiece => 'Aucune pièce déposée.';

  @override
  String get occupantsTeleverserPiece => 'Ajouter une photo';

  @override
  String get etatsDesLieuxTitre => 'États des lieux';

  @override
  String get etatsDesLieuxEtablirEntree => 'Établir l’état des lieux d’entrée';

  @override
  String get etatsDesLieuxEtablirSortie => 'Établir l’état des lieux de sortie';

  @override
  String get etatsDesLieuxSigne => 'Signé';

  @override
  String get etatsDesLieuxNonSigne => 'Non signé';

  @override
  String get etatsDesLieuxLibelle => 'Libellé (ex. Salon)';

  @override
  String get etatsDesLieuxObservation => 'Observation';

  @override
  String get etatsDesLieuxAjouterLigne => 'Ajouter la ligne';

  @override
  String get etatsDesLieuxAjouterPhoto => 'Photo';

  @override
  String get etatsDesLieuxSignature => 'Signature';

  @override
  String get etatsDesLieuxValiderSignature => 'Valider la signature';

  @override
  String get etatsDesLieuxEffacerSignature => 'Effacer';

  @override
  String get etatsDesLieuxSignatureObligatoire =>
      'Faites signer avant de valider.';

  @override
  String get missionsTitre => 'Mes missions de ménage';

  @override
  String get missionsAucune => 'Aucune mission.';

  @override
  String get missionsTousLesStatuts => 'Tous les statuts';

  @override
  String get missionsStatutAFaire => 'À faire';

  @override
  String get missionsStatutEnCours => 'En cours';

  @override
  String get missionsStatutFaite => 'Faite';

  @override
  String get missionsDemarrer => 'Démarrer';

  @override
  String get missionsTerminer => 'Terminer';

  @override
  String get missionsTerminerTitre => 'Terminer la mission';

  @override
  String get missionsNotes => 'Notes (optionnel)';

  @override
  String get missionsEcheance => 'Échéance';

  @override
  String get missionsSansLogement => 'Logement non précisé';

  @override
  String get gainsTitre => 'Mes gains';

  @override
  String get gainsBientotDisponible =>
      'Le suivi de vos gains n’est pas encore disponible dans cette version.';
}
