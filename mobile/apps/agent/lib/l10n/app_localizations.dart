import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_fr.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of Libelles
/// returned by `Libelles.of(context)`.
///
/// Applications need to include `Libelles.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: Libelles.localizationsDelegates,
///   supportedLocales: Libelles.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the Libelles.supportedLocales
/// property.
abstract class Libelles {
  Libelles(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static Libelles of(BuildContext context) {
    return Localizations.of<Libelles>(context, Libelles)!;
  }

  static const LocalizationsDelegate<Libelles> delegate = _LibellesDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('fr'),
  ];

  /// No description provided for @marque.
  ///
  /// In fr, this message translates to:
  /// **'Agent de terrain'**
  String get marque;

  /// No description provided for @reessayer.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
  String get reessayer;

  /// No description provided for @erreurGenerique.
  ///
  /// In fr, this message translates to:
  /// **'Une erreur est survenue. Réessayez dans un instant.'**
  String get erreurGenerique;

  /// No description provided for @champObligatoire.
  ///
  /// In fr, this message translates to:
  /// **'Ce champ est obligatoire.'**
  String get champObligatoire;

  /// No description provided for @annuler.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get annuler;

  /// No description provided for @valider.
  ///
  /// In fr, this message translates to:
  /// **'Valider'**
  String get valider;

  /// No description provided for @deconnexion.
  ///
  /// In fr, this message translates to:
  /// **'Déconnexion'**
  String get deconnexion;

  /// No description provided for @connexionTitre.
  ///
  /// In fr, this message translates to:
  /// **'Connexion agent'**
  String get connexionTitre;

  /// No description provided for @champIdentifiant.
  ///
  /// In fr, this message translates to:
  /// **'Email ou téléphone'**
  String get champIdentifiant;

  /// No description provided for @champMotDePasse.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get champMotDePasse;

  /// No description provided for @boutonSeConnecter.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get boutonSeConnecter;

  /// No description provided for @erreurProfilInvalide.
  ///
  /// In fr, this message translates to:
  /// **'Ce compte n’est pas un compte agent de terrain.'**
  String get erreurProfilInvalide;

  /// No description provided for @bienvenue.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour, {prenom}'**
  String bienvenue(String prenom);

  /// No description provided for @kpiArrivees.
  ///
  /// In fr, this message translates to:
  /// **'Arrivées à accueillir'**
  String get kpiArrivees;

  /// No description provided for @kpiDeparts.
  ///
  /// In fr, this message translates to:
  /// **'Départs à faire sortir'**
  String get kpiDeparts;

  /// No description provided for @kpiMissions.
  ///
  /// In fr, this message translates to:
  /// **'Missions à faire'**
  String get kpiMissions;

  /// No description provided for @menuSejours.
  ///
  /// In fr, this message translates to:
  /// **'Mes séjours'**
  String get menuSejours;

  /// No description provided for @menuMissions.
  ///
  /// In fr, this message translates to:
  /// **'Mes missions'**
  String get menuMissions;

  /// No description provided for @menuGains.
  ///
  /// In fr, this message translates to:
  /// **'Mes gains'**
  String get menuGains;

  /// No description provided for @sejoursTitre.
  ///
  /// In fr, this message translates to:
  /// **'Mes séjours du jour'**
  String get sejoursTitre;

  /// No description provided for @sejoursAucun.
  ///
  /// In fr, this message translates to:
  /// **'Aucun séjour aujourd’hui.'**
  String get sejoursAucun;

  /// No description provided for @sejoursClient.
  ///
  /// In fr, this message translates to:
  /// **'Client'**
  String get sejoursClient;

  /// No description provided for @sejoursLogement.
  ///
  /// In fr, this message translates to:
  /// **'Logement'**
  String get sejoursLogement;

  /// No description provided for @sejoursArrivee.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée'**
  String get sejoursArrivee;

  /// No description provided for @sejoursDepart.
  ///
  /// In fr, this message translates to:
  /// **'Départ'**
  String get sejoursDepart;

  /// No description provided for @sejourIntrouvable.
  ///
  /// In fr, this message translates to:
  /// **'Ce séjour n’est pas disponible.'**
  String get sejourIntrouvable;

  /// No description provided for @detailRetour.
  ///
  /// In fr, this message translates to:
  /// **'← Retour à la liste'**
  String get detailRetour;

  /// No description provided for @detailNetAPayer.
  ///
  /// In fr, this message translates to:
  /// **'Net à payer'**
  String get detailNetAPayer;

  /// No description provided for @detailSejour.
  ///
  /// In fr, this message translates to:
  /// **'Séjour'**
  String get detailSejour;

  /// No description provided for @detailNuits.
  ///
  /// In fr, this message translates to:
  /// **'{count} nuit(s)'**
  String detailNuits(int count);

  /// No description provided for @detailOccupants.
  ///
  /// In fr, this message translates to:
  /// **'Occupants'**
  String get detailOccupants;

  /// No description provided for @detailOccupantsAdultes.
  ///
  /// In fr, this message translates to:
  /// **'{count} adulte(s)'**
  String detailOccupantsAdultes(int count);

  /// No description provided for @detailEtEnfants.
  ///
  /// In fr, this message translates to:
  /// **', {count} enfant(s)'**
  String detailEtEnfants(int count);

  /// No description provided for @detailClient.
  ///
  /// In fr, this message translates to:
  /// **'Client'**
  String get detailClient;

  /// No description provided for @detailPaiement.
  ///
  /// In fr, this message translates to:
  /// **'Paiement'**
  String get detailPaiement;

  /// No description provided for @detailEncaisse.
  ///
  /// In fr, this message translates to:
  /// **'Encaissé'**
  String get detailEncaisse;

  /// No description provided for @detailResteDu.
  ///
  /// In fr, this message translates to:
  /// **'Reste dû'**
  String get detailResteDu;

  /// No description provided for @detailCaution.
  ///
  /// In fr, this message translates to:
  /// **'Caution'**
  String get detailCaution;

  /// No description provided for @detailCautionRetenue.
  ///
  /// In fr, this message translates to:
  /// **'Caution retenue'**
  String get detailCautionRetenue;

  /// No description provided for @detailNoShow.
  ///
  /// In fr, this message translates to:
  /// **'Signalé absent le {date}'**
  String detailNoShow(String date);

  /// No description provided for @detailCautionRetenueInfo.
  ///
  /// In fr, this message translates to:
  /// **'Caution retenue : {montant}'**
  String detailCautionRetenueInfo(String montant);

  /// No description provided for @checkInAction.
  ///
  /// In fr, this message translates to:
  /// **'Faire le check-in'**
  String get checkInAction;

  /// No description provided for @checkInTitre.
  ///
  /// In fr, this message translates to:
  /// **'Check-in'**
  String get checkInTitre;

  /// No description provided for @checkInAide.
  ///
  /// In fr, this message translates to:
  /// **'Le client vous communique son code d’arrivée : saisissez-le ci-dessous.'**
  String get checkInAide;

  /// No description provided for @checkInCode.
  ///
  /// In fr, this message translates to:
  /// **'Code d’arrivée'**
  String get checkInCode;

  /// No description provided for @checkInConfirmer.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer le check-in'**
  String get checkInConfirmer;

  /// No description provided for @checkOutAction.
  ///
  /// In fr, this message translates to:
  /// **'Faire le check-out'**
  String get checkOutAction;

  /// No description provided for @checkOutTitre.
  ///
  /// In fr, this message translates to:
  /// **'Check-out'**
  String get checkOutTitre;

  /// No description provided for @checkOutConsommations.
  ///
  /// In fr, this message translates to:
  /// **'Consommations facturées'**
  String get checkOutConsommations;

  /// No description provided for @checkOutHebergement.
  ///
  /// In fr, this message translates to:
  /// **'Hébergement'**
  String get checkOutHebergement;

  /// No description provided for @checkOutRepas.
  ///
  /// In fr, this message translates to:
  /// **'Repas'**
  String get checkOutRepas;

  /// No description provided for @checkOutTransferts.
  ///
  /// In fr, this message translates to:
  /// **'Transferts'**
  String get checkOutTransferts;

  /// No description provided for @checkOutTotal.
  ///
  /// In fr, this message translates to:
  /// **'Total'**
  String get checkOutTotal;

  /// No description provided for @checkOutAucuneConsommation.
  ///
  /// In fr, this message translates to:
  /// **'Aucune consommation à facturer.'**
  String get checkOutAucuneConsommation;

  /// No description provided for @checkOutCautionRetenue.
  ///
  /// In fr, this message translates to:
  /// **'Caution retenue'**
  String get checkOutCautionRetenue;

  /// No description provided for @checkOutMotif.
  ///
  /// In fr, this message translates to:
  /// **'Motif'**
  String get checkOutMotif;

  /// No description provided for @checkOutMotifObligatoire.
  ///
  /// In fr, this message translates to:
  /// **'Un motif est obligatoire dès qu’une caution est retenue.'**
  String get checkOutMotifObligatoire;

  /// No description provided for @checkOutConfirmer.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer le check-out'**
  String get checkOutConfirmer;

  /// No description provided for @occupantsTitre.
  ///
  /// In fr, this message translates to:
  /// **'Occupants — fiche de police'**
  String get occupantsTitre;

  /// No description provided for @occupantsNom.
  ///
  /// In fr, this message translates to:
  /// **'Nom'**
  String get occupantsNom;

  /// No description provided for @occupantsEnfant.
  ///
  /// In fr, this message translates to:
  /// **'enfant'**
  String get occupantsEnfant;

  /// No description provided for @occupantsTypePiece.
  ///
  /// In fr, this message translates to:
  /// **'Type de pièce'**
  String get occupantsTypePiece;

  /// No description provided for @occupantsTelephone.
  ///
  /// In fr, this message translates to:
  /// **'Téléphone'**
  String get occupantsTelephone;

  /// No description provided for @occupantsPiece.
  ///
  /// In fr, this message translates to:
  /// **'Pièce d’identité'**
  String get occupantsPiece;

  /// No description provided for @occupantsAucunePiece.
  ///
  /// In fr, this message translates to:
  /// **'Aucune pièce déposée.'**
  String get occupantsAucunePiece;

  /// No description provided for @occupantsTeleverserPiece.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter une photo'**
  String get occupantsTeleverserPiece;

  /// No description provided for @etatsDesLieuxTitre.
  ///
  /// In fr, this message translates to:
  /// **'États des lieux'**
  String get etatsDesLieuxTitre;

  /// No description provided for @etatsDesLieuxEtablirEntree.
  ///
  /// In fr, this message translates to:
  /// **'Établir l’état des lieux d’entrée'**
  String get etatsDesLieuxEtablirEntree;

  /// No description provided for @etatsDesLieuxEtablirSortie.
  ///
  /// In fr, this message translates to:
  /// **'Établir l’état des lieux de sortie'**
  String get etatsDesLieuxEtablirSortie;

  /// No description provided for @etatsDesLieuxSigne.
  ///
  /// In fr, this message translates to:
  /// **'Signé'**
  String get etatsDesLieuxSigne;

  /// No description provided for @etatsDesLieuxNonSigne.
  ///
  /// In fr, this message translates to:
  /// **'Non signé'**
  String get etatsDesLieuxNonSigne;

  /// No description provided for @etatsDesLieuxLibelle.
  ///
  /// In fr, this message translates to:
  /// **'Libellé (ex. Salon)'**
  String get etatsDesLieuxLibelle;

  /// No description provided for @etatsDesLieuxObservation.
  ///
  /// In fr, this message translates to:
  /// **'Observation'**
  String get etatsDesLieuxObservation;

  /// No description provided for @etatsDesLieuxAjouterLigne.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter la ligne'**
  String get etatsDesLieuxAjouterLigne;

  /// No description provided for @etatsDesLieuxAjouterPhoto.
  ///
  /// In fr, this message translates to:
  /// **'Photo'**
  String get etatsDesLieuxAjouterPhoto;

  /// No description provided for @etatsDesLieuxSignature.
  ///
  /// In fr, this message translates to:
  /// **'Signature'**
  String get etatsDesLieuxSignature;

  /// No description provided for @etatsDesLieuxValiderSignature.
  ///
  /// In fr, this message translates to:
  /// **'Valider la signature'**
  String get etatsDesLieuxValiderSignature;

  /// No description provided for @etatsDesLieuxEffacerSignature.
  ///
  /// In fr, this message translates to:
  /// **'Effacer'**
  String get etatsDesLieuxEffacerSignature;

  /// No description provided for @etatsDesLieuxSignatureObligatoire.
  ///
  /// In fr, this message translates to:
  /// **'Faites signer avant de valider.'**
  String get etatsDesLieuxSignatureObligatoire;

  /// No description provided for @missionsTitre.
  ///
  /// In fr, this message translates to:
  /// **'Mes missions de ménage'**
  String get missionsTitre;

  /// No description provided for @missionsAucune.
  ///
  /// In fr, this message translates to:
  /// **'Aucune mission.'**
  String get missionsAucune;

  /// No description provided for @missionsTousLesStatuts.
  ///
  /// In fr, this message translates to:
  /// **'Tous les statuts'**
  String get missionsTousLesStatuts;

  /// No description provided for @missionsStatutAFaire.
  ///
  /// In fr, this message translates to:
  /// **'À faire'**
  String get missionsStatutAFaire;

  /// No description provided for @missionsStatutEnCours.
  ///
  /// In fr, this message translates to:
  /// **'En cours'**
  String get missionsStatutEnCours;

  /// No description provided for @missionsStatutFaite.
  ///
  /// In fr, this message translates to:
  /// **'Faite'**
  String get missionsStatutFaite;

  /// No description provided for @missionsDemarrer.
  ///
  /// In fr, this message translates to:
  /// **'Démarrer'**
  String get missionsDemarrer;

  /// No description provided for @missionsTerminer.
  ///
  /// In fr, this message translates to:
  /// **'Terminer'**
  String get missionsTerminer;

  /// No description provided for @missionsTerminerTitre.
  ///
  /// In fr, this message translates to:
  /// **'Terminer la mission'**
  String get missionsTerminerTitre;

  /// No description provided for @missionsNotes.
  ///
  /// In fr, this message translates to:
  /// **'Notes (optionnel)'**
  String get missionsNotes;

  /// No description provided for @missionsEcheance.
  ///
  /// In fr, this message translates to:
  /// **'Échéance'**
  String get missionsEcheance;

  /// No description provided for @missionsSansLogement.
  ///
  /// In fr, this message translates to:
  /// **'Logement non précisé'**
  String get missionsSansLogement;

  /// No description provided for @gainsTitre.
  ///
  /// In fr, this message translates to:
  /// **'Mes gains'**
  String get gainsTitre;

  /// No description provided for @gainsBientotDisponible.
  ///
  /// In fr, this message translates to:
  /// **'Le suivi de vos gains n’est pas encore disponible dans cette version.'**
  String get gainsBientotDisponible;
}

class _LibellesDelegate extends LocalizationsDelegate<Libelles> {
  const _LibellesDelegate();

  @override
  Future<Libelles> load(Locale locale) {
    return SynchronousFuture<Libelles>(lookupLibelles(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'fr'].contains(locale.languageCode);

  @override
  bool shouldReload(_LibellesDelegate old) => false;
}

Libelles lookupLibelles(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return LibellesEn();
    case 'fr':
      return LibellesFr();
  }

  throw FlutterError(
    'Libelles.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
