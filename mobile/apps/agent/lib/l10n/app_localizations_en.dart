// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class LibellesEn extends Libelles {
  LibellesEn([String locale = 'en']) : super(locale);

  @override
  String get marque => 'Field agent';

  @override
  String get reessayer => 'Try again';

  @override
  String get erreurGenerique =>
      'Something went wrong. Please try again shortly.';

  @override
  String get champObligatoire => 'This field is required.';

  @override
  String get annuler => 'Cancel';

  @override
  String get valider => 'Confirm';

  @override
  String get deconnexion => 'Log out';

  @override
  String get connexionTitre => 'Agent sign in';

  @override
  String get champIdentifiant => 'Email or phone';

  @override
  String get champMotDePasse => 'Password';

  @override
  String get boutonSeConnecter => 'Sign in';

  @override
  String get erreurProfilInvalide =>
      'This account is not a field agent account.';

  @override
  String bienvenue(String prenom) {
    return 'Hello, $prenom';
  }

  @override
  String get kpiArrivees => 'Arrivals to welcome';

  @override
  String get kpiDeparts => 'Departures to check out';

  @override
  String get kpiMissions => 'Tasks to do';

  @override
  String get menuSejours => 'My stays';

  @override
  String get menuMissions => 'My tasks';

  @override
  String get menuGains => 'My earnings';

  @override
  String get sejoursTitre => 'My stays today';

  @override
  String get sejoursAucun => 'No stays today.';

  @override
  String get sejoursClient => 'Client';

  @override
  String get sejoursLogement => 'Home';

  @override
  String get sejoursArrivee => 'Check-in';

  @override
  String get sejoursDepart => 'Check-out';

  @override
  String get sejourIntrouvable => 'This stay isn\'t available.';

  @override
  String get detailRetour => '← Back to list';

  @override
  String get detailNetAPayer => 'Net amount due';

  @override
  String get detailSejour => 'Stay';

  @override
  String detailNuits(int count) {
    return '$count night(s)';
  }

  @override
  String get detailOccupants => 'Occupants';

  @override
  String detailOccupantsAdultes(int count) {
    return '$count adult(s)';
  }

  @override
  String detailEtEnfants(int count) {
    return ', $count child(ren)';
  }

  @override
  String get detailClient => 'Client';

  @override
  String get detailPaiement => 'Payment';

  @override
  String get detailEncaisse => 'Collected';

  @override
  String get detailResteDu => 'Balance due';

  @override
  String get detailCaution => 'Security deposit';

  @override
  String get detailCautionRetenue => 'Deposit withheld';

  @override
  String detailNoShow(String date) {
    return 'Reported as no-show on $date';
  }

  @override
  String detailCautionRetenueInfo(String montant) {
    return 'Deposit withheld: $montant';
  }

  @override
  String get checkInAction => 'Do check-in';

  @override
  String get checkInTitre => 'Check-in';

  @override
  String get checkInAide =>
      'The guest gives you their arrival code: enter it below.';

  @override
  String get checkInCode => 'Arrival code';

  @override
  String get checkInConfirmer => 'Confirm check-in';

  @override
  String get checkOutAction => 'Do check-out';

  @override
  String get checkOutTitre => 'Check-out';

  @override
  String get checkOutConsommations => 'Charges to bill';

  @override
  String get checkOutHebergement => 'Accommodation';

  @override
  String get checkOutRepas => 'Meals';

  @override
  String get checkOutTransferts => 'Transfers';

  @override
  String get checkOutTotal => 'Total';

  @override
  String get checkOutAucuneConsommation => 'Nothing to bill.';

  @override
  String get checkOutCautionRetenue => 'Deposit withheld';

  @override
  String get checkOutMotif => 'Reason';

  @override
  String get checkOutMotifObligatoire =>
      'A reason is required once a deposit is withheld.';

  @override
  String get checkOutConfirmer => 'Confirm check-out';

  @override
  String get occupantsTitre => 'Occupants — police record';

  @override
  String get occupantsNom => 'Name';

  @override
  String get occupantsEnfant => 'child';

  @override
  String get occupantsTypePiece => 'ID type';

  @override
  String get occupantsTelephone => 'Phone';

  @override
  String get occupantsPiece => 'ID document';

  @override
  String get occupantsAucunePiece => 'No document uploaded.';

  @override
  String get occupantsTeleverserPiece => 'Add a photo';

  @override
  String get etatsDesLieuxTitre => 'Condition reports';

  @override
  String get etatsDesLieuxEtablirEntree => 'Start the check-in report';

  @override
  String get etatsDesLieuxEtablirSortie => 'Start the check-out report';

  @override
  String get etatsDesLieuxSigne => 'Signed';

  @override
  String get etatsDesLieuxNonSigne => 'Not signed';

  @override
  String get etatsDesLieuxLibelle => 'Label (e.g. Living room)';

  @override
  String get etatsDesLieuxObservation => 'Note';

  @override
  String get etatsDesLieuxAjouterLigne => 'Add line';

  @override
  String get etatsDesLieuxAjouterPhoto => 'Photo';

  @override
  String get etatsDesLieuxSignature => 'Signature';

  @override
  String get etatsDesLieuxValiderSignature => 'Confirm signature';

  @override
  String get etatsDesLieuxEffacerSignature => 'Clear';

  @override
  String get etatsDesLieuxSignatureObligatoire =>
      'Get it signed before confirming.';

  @override
  String get missionsTitre => 'My housekeeping tasks';

  @override
  String get missionsAucune => 'No tasks.';

  @override
  String get missionsTousLesStatuts => 'All statuses';

  @override
  String get missionsStatutAFaire => 'To do';

  @override
  String get missionsStatutEnCours => 'In progress';

  @override
  String get missionsStatutFaite => 'Done';

  @override
  String get missionsDemarrer => 'Start';

  @override
  String get missionsTerminer => 'Finish';

  @override
  String get missionsTerminerTitre => 'Finish the task';

  @override
  String get missionsNotes => 'Notes (optional)';

  @override
  String get missionsEcheance => 'Due';

  @override
  String get missionsSansLogement => 'No home specified';

  @override
  String get gainsTitre => 'My earnings';

  @override
  String get gainsBientotDisponible =>
      'Earnings tracking isn\'t available in this version yet.';
}
