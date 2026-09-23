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
  /// **'Résidences meublées'**
  String get marque;

  /// No description provided for @accueilTitre.
  ///
  /// In fr, this message translates to:
  /// **'Votre résidence meublée à Abidjan'**
  String get accueilTitre;

  /// No description provided for @accueilSousTitre.
  ///
  /// In fr, this message translates to:
  /// **'Studios, appartements et villas, avec accueil, ménage et services sur place.'**
  String get accueilSousTitre;

  /// No description provided for @reessayer.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
  String get reessayer;

  /// No description provided for @connexion.
  ///
  /// In fr, this message translates to:
  /// **'Connexion'**
  String get connexion;

  /// No description provided for @inscription.
  ///
  /// In fr, this message translates to:
  /// **'Créer un compte'**
  String get inscription;

  /// No description provided for @deconnexion.
  ///
  /// In fr, this message translates to:
  /// **'Déconnexion'**
  String get deconnexion;

  /// No description provided for @bonjourUtilisateur.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour, {prenom}'**
  String bonjourUtilisateur(String prenom);

  /// No description provided for @chargementEnCours.
  ///
  /// In fr, this message translates to:
  /// **'Chargement…'**
  String get chargementEnCours;

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

  /// No description provided for @prixParNuit.
  ///
  /// In fr, this message translates to:
  /// **'{prix} / nuit'**
  String prixParNuit(String prix);

  /// No description provided for @prixSurDemande.
  ///
  /// In fr, this message translates to:
  /// **'Prix sur demande'**
  String get prixSurDemande;

  /// No description provided for @accueilLogementsMisEnAvant.
  ///
  /// In fr, this message translates to:
  /// **'Logements mis en avant'**
  String get accueilLogementsMisEnAvant;

  /// No description provided for @accueilAucunLogement.
  ///
  /// In fr, this message translates to:
  /// **'Aucun logement mis en avant pour le moment.'**
  String get accueilAucunLogement;

  /// No description provided for @connexionTitre.
  ///
  /// In fr, this message translates to:
  /// **'Connexion'**
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

  /// No description provided for @pasDeCompte.
  ///
  /// In fr, this message translates to:
  /// **'Pas encore de compte ?'**
  String get pasDeCompte;

  /// No description provided for @creerUnCompte.
  ///
  /// In fr, this message translates to:
  /// **'Créer un compte'**
  String get creerUnCompte;

  /// No description provided for @inscriptionTitre.
  ///
  /// In fr, this message translates to:
  /// **'Créer un compte'**
  String get inscriptionTitre;

  /// No description provided for @champNom.
  ///
  /// In fr, this message translates to:
  /// **'Nom'**
  String get champNom;

  /// No description provided for @champPrenoms.
  ///
  /// In fr, this message translates to:
  /// **'Prénoms'**
  String get champPrenoms;

  /// No description provided for @champEmail.
  ///
  /// In fr, this message translates to:
  /// **'Email'**
  String get champEmail;

  /// No description provided for @champTelephone.
  ///
  /// In fr, this message translates to:
  /// **'Téléphone (optionnel)'**
  String get champTelephone;

  /// No description provided for @champMotDePasseConfirmation.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer le mot de passe'**
  String get champMotDePasseConfirmation;

  /// No description provided for @conditionsAcceptees.
  ///
  /// In fr, this message translates to:
  /// **'J’accepte les conditions générales d’utilisation.'**
  String get conditionsAcceptees;

  /// No description provided for @conditionsObligatoires.
  ///
  /// In fr, this message translates to:
  /// **'Vous devez accepter les conditions générales.'**
  String get conditionsObligatoires;

  /// No description provided for @motsDePasseDifferents.
  ///
  /// In fr, this message translates to:
  /// **'Les mots de passe ne correspondent pas.'**
  String get motsDePasseDifferents;

  /// No description provided for @boutonCreerCompte.
  ///
  /// In fr, this message translates to:
  /// **'Créer mon compte'**
  String get boutonCreerCompte;

  /// No description provided for @dejaUnCompte.
  ///
  /// In fr, this message translates to:
  /// **'Déjà un compte ?'**
  String get dejaUnCompte;

  /// No description provided for @seConnecter.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get seConnecter;

  /// No description provided for @inscriptionSucces.
  ///
  /// In fr, this message translates to:
  /// **'Compte créé. Vérifiez votre email pour l’activer.'**
  String get inscriptionSucces;

  /// No description provided for @rechercheTitre.
  ///
  /// In fr, this message translates to:
  /// **'Rechercher un logement'**
  String get rechercheTitre;

  /// No description provided for @champCommune.
  ///
  /// In fr, this message translates to:
  /// **'Commune'**
  String get champCommune;

  /// No description provided for @toutesCommunes.
  ///
  /// In fr, this message translates to:
  /// **'Toutes les communes'**
  String get toutesCommunes;

  /// No description provided for @champArrivee.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée'**
  String get champArrivee;

  /// No description provided for @champDepart.
  ///
  /// In fr, this message translates to:
  /// **'Départ'**
  String get champDepart;

  /// No description provided for @boutonRechercher.
  ///
  /// In fr, this message translates to:
  /// **'Rechercher'**
  String get boutonRechercher;

  /// No description provided for @rechercheAucunResultat.
  ///
  /// In fr, this message translates to:
  /// **'Aucun logement ne correspond à votre recherche.'**
  String get rechercheAucunResultat;

  /// No description provided for @rechercheResultats.
  ///
  /// In fr, this message translates to:
  /// **'{total} logement(s) trouvé(s)'**
  String rechercheResultats(int total);

  /// No description provided for @ficheIntrouvable.
  ///
  /// In fr, this message translates to:
  /// **'Ce logement n’est pas disponible.'**
  String get ficheIntrouvable;

  /// No description provided for @ficheCapacite.
  ///
  /// In fr, this message translates to:
  /// **'Jusqu’à {capacite} personnes'**
  String ficheCapacite(int capacite);

  /// No description provided for @ficheEquipements.
  ///
  /// In fr, this message translates to:
  /// **'Équipements'**
  String get ficheEquipements;

  /// No description provided for @ficheRegles.
  ///
  /// In fr, this message translates to:
  /// **'Règles de la maison'**
  String get ficheRegles;

  /// No description provided for @ficheTarif.
  ///
  /// In fr, this message translates to:
  /// **'Tarif'**
  String get ficheTarif;

  /// No description provided for @ficheReserver.
  ///
  /// In fr, this message translates to:
  /// **'Réserver'**
  String get ficheReserver;

  /// No description provided for @ficheBientotDisponible.
  ///
  /// In fr, this message translates to:
  /// **'Bientôt disponible'**
  String get ficheBientotDisponible;

  /// No description provided for @libelleChambres.
  ///
  /// In fr, this message translates to:
  /// **'Chambres'**
  String get libelleChambres;

  /// No description provided for @libelleLits.
  ///
  /// In fr, this message translates to:
  /// **'Lits'**
  String get libelleLits;

  /// No description provided for @libelleSallesDeBain.
  ///
  /// In fr, this message translates to:
  /// **'Salles de bain'**
  String get libelleSallesDeBain;

  /// No description provided for @libelleSurface.
  ///
  /// In fr, this message translates to:
  /// **'Surface'**
  String get libelleSurface;

  /// No description provided for @libelleCaution.
  ///
  /// In fr, this message translates to:
  /// **'Caution'**
  String get libelleCaution;

  /// No description provided for @libelleAnnulation.
  ///
  /// In fr, this message translates to:
  /// **'Politique d’annulation'**
  String get libelleAnnulation;
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
