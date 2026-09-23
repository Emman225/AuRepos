// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for French (`fr`).
class LibellesFr extends Libelles {
  LibellesFr([String locale = 'fr']) : super(locale);

  @override
  String get marque => 'Résidences meublées';

  @override
  String get accueilTitre => 'Votre résidence meublée à Abidjan';

  @override
  String get accueilSousTitre =>
      'Studios, appartements et villas, avec accueil, ménage et services sur place.';

  @override
  String get reessayer => 'Réessayer';

  @override
  String get connexion => 'Connexion';

  @override
  String get inscription => 'Créer un compte';

  @override
  String get deconnexion => 'Déconnexion';

  @override
  String bonjourUtilisateur(String prenom) {
    return 'Bonjour, $prenom';
  }

  @override
  String get chargementEnCours => 'Chargement…';

  @override
  String get erreurGenerique =>
      'Une erreur est survenue. Réessayez dans un instant.';

  @override
  String get champObligatoire => 'Ce champ est obligatoire.';

  @override
  String prixParNuit(String prix) {
    return '$prix / nuit';
  }

  @override
  String get prixSurDemande => 'Prix sur demande';

  @override
  String get accueilLogementsMisEnAvant => 'Logements mis en avant';

  @override
  String get accueilAucunLogement =>
      'Aucun logement mis en avant pour le moment.';

  @override
  String get connexionTitre => 'Connexion';

  @override
  String get champIdentifiant => 'Email ou téléphone';

  @override
  String get champMotDePasse => 'Mot de passe';

  @override
  String get boutonSeConnecter => 'Se connecter';

  @override
  String get pasDeCompte => 'Pas encore de compte ?';

  @override
  String get creerUnCompte => 'Créer un compte';

  @override
  String get inscriptionTitre => 'Créer un compte';

  @override
  String get champNom => 'Nom';

  @override
  String get champPrenoms => 'Prénoms';

  @override
  String get champEmail => 'Email';

  @override
  String get champTelephone => 'Téléphone (optionnel)';

  @override
  String get champMotDePasseConfirmation => 'Confirmer le mot de passe';

  @override
  String get conditionsAcceptees =>
      'J’accepte les conditions générales d’utilisation.';

  @override
  String get conditionsObligatoires =>
      'Vous devez accepter les conditions générales.';

  @override
  String get motsDePasseDifferents => 'Les mots de passe ne correspondent pas.';

  @override
  String get boutonCreerCompte => 'Créer mon compte';

  @override
  String get dejaUnCompte => 'Déjà un compte ?';

  @override
  String get seConnecter => 'Se connecter';

  @override
  String get inscriptionSucces =>
      'Compte créé. Vérifiez votre email pour l’activer.';

  @override
  String get rechercheTitre => 'Rechercher un logement';

  @override
  String get champCommune => 'Commune';

  @override
  String get toutesCommunes => 'Toutes les communes';

  @override
  String get champArrivee => 'Arrivée';

  @override
  String get champDepart => 'Départ';

  @override
  String get boutonRechercher => 'Rechercher';

  @override
  String get rechercheAucunResultat =>
      'Aucun logement ne correspond à votre recherche.';

  @override
  String rechercheResultats(int total) {
    return '$total logement(s) trouvé(s)';
  }

  @override
  String get ficheIntrouvable => 'Ce logement n’est pas disponible.';

  @override
  String ficheCapacite(int capacite) {
    return 'Jusqu’à $capacite personnes';
  }

  @override
  String get ficheEquipements => 'Équipements';

  @override
  String get ficheRegles => 'Règles de la maison';

  @override
  String get ficheTarif => 'Tarif';

  @override
  String get ficheReserver => 'Réserver';

  @override
  String get ficheBientotDisponible => 'Bientôt disponible';

  @override
  String get libelleChambres => 'Chambres';

  @override
  String get libelleLits => 'Lits';

  @override
  String get libelleSallesDeBain => 'Salles de bain';

  @override
  String get libelleSurface => 'Surface';

  @override
  String get libelleCaution => 'Caution';

  @override
  String get libelleAnnulation => 'Politique d’annulation';
}
