import 'package:go_router/go_router.dart';

import 'ecrans/accueil/accueil_ecran.dart';
import 'ecrans/connexion/connexion_ecran.dart';
import 'ecrans/fiche_logement/fiche_logement_ecran.dart';
import 'ecrans/inscription/inscription_ecran.dart';
import 'ecrans/recherche/recherche_ecran.dart';

/// Squelette de navigation de cette tranche (P2-MOB-03) : accueil,
/// connexion, inscription, recherche et fiche logement. Le tunnel de
/// réservation (calendrier, devis, paiement) et « Mon espace » n'ont pas de
/// route ici — ils sont hors périmètre, voir le rapport de livraison.
final GoRouter routeur = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(path: '/', builder: (context, state) => const AccueilEcran()),
    GoRoute(path: '/connexion', builder: (context, state) => const ConnexionEcran()),
    GoRoute(path: '/inscription', builder: (context, state) => const InscriptionEcran()),
    GoRoute(path: '/recherche', builder: (context, state) => const RechercheEcran()),
    GoRoute(
      path: '/logements/:reference',
      builder: (context, state) => FicheLogementEcran(reference: state.pathParameters['reference']!),
    ),
  ],
);
