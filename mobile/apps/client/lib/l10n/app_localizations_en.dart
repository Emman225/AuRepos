// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class LibellesEn extends Libelles {
  LibellesEn([String locale = 'en']) : super(locale);

  @override
  String get marque => 'Résidences meublées';

  @override
  String get accueilTitre => 'Your furnished residence in Abidjan';

  @override
  String get accueilSousTitre =>
      'Studios, apartments and villas, with on-site reception, housekeeping and services.';

  @override
  String get reessayer => 'Try again';

  @override
  String get connexion => 'Sign in';

  @override
  String get inscription => 'Create an account';

  @override
  String get deconnexion => 'Log out';

  @override
  String bonjourUtilisateur(String prenom) {
    return 'Hello, $prenom';
  }

  @override
  String get chargementEnCours => 'Loading…';

  @override
  String get erreurGenerique =>
      'Something went wrong. Please try again shortly.';

  @override
  String get champObligatoire => 'This field is required.';

  @override
  String prixParNuit(String prix) {
    return '$prix / night';
  }

  @override
  String get prixSurDemande => 'Price on request';

  @override
  String get accueilLogementsMisEnAvant => 'Featured homes';

  @override
  String get accueilAucunLogement => 'No featured homes right now.';

  @override
  String get connexionTitre => 'Sign in';

  @override
  String get champIdentifiant => 'Email or phone';

  @override
  String get champMotDePasse => 'Password';

  @override
  String get boutonSeConnecter => 'Sign in';

  @override
  String get pasDeCompte => 'Don\'t have an account yet?';

  @override
  String get creerUnCompte => 'Create an account';

  @override
  String get inscriptionTitre => 'Create an account';

  @override
  String get champNom => 'Last name';

  @override
  String get champPrenoms => 'First name(s)';

  @override
  String get champEmail => 'Email';

  @override
  String get champTelephone => 'Phone (optional)';

  @override
  String get champMotDePasseConfirmation => 'Confirm password';

  @override
  String get conditionsAcceptees => 'I accept the terms of use.';

  @override
  String get conditionsObligatoires => 'You must accept the terms of use.';

  @override
  String get motsDePasseDifferents => 'Passwords do not match.';

  @override
  String get boutonCreerCompte => 'Create my account';

  @override
  String get dejaUnCompte => 'Already have an account?';

  @override
  String get seConnecter => 'Sign in';

  @override
  String get inscriptionSucces =>
      'Account created. Check your email to activate it.';

  @override
  String get rechercheTitre => 'Search for a home';

  @override
  String get champCommune => 'Commune';

  @override
  String get toutesCommunes => 'All communes';

  @override
  String get champArrivee => 'Check-in';

  @override
  String get champDepart => 'Check-out';

  @override
  String get boutonRechercher => 'Search';

  @override
  String get rechercheAucunResultat => 'No home matches your search.';

  @override
  String rechercheResultats(int total) {
    return '$total home(s) found';
  }

  @override
  String get ficheIntrouvable => 'This home isn\'t available.';

  @override
  String ficheCapacite(int capacite) {
    return 'Up to $capacite guests';
  }

  @override
  String get ficheEquipements => 'Amenities';

  @override
  String get ficheRegles => 'House rules';

  @override
  String get ficheTarif => 'Rate';

  @override
  String get ficheReserver => 'Book';

  @override
  String get ficheBientotDisponible => 'Coming soon';

  @override
  String get libelleChambres => 'Bedrooms';

  @override
  String get libelleLits => 'Beds';

  @override
  String get libelleSallesDeBain => 'Bathrooms';

  @override
  String get libelleSurface => 'Surface area';

  @override
  String get libelleCaution => 'Security deposit';

  @override
  String get libelleAnnulation => 'Cancellation policy';
}
