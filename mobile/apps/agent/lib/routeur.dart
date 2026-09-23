import 'package:go_router/go_router.dart';

import 'ecrans/connexion/connexion_ecran.dart';
import 'ecrans/gains/gains_ecran.dart';
import 'ecrans/missions/missions_ecran.dart';
import 'ecrans/sejour_detail/sejour_detail_ecran.dart';
import 'ecrans/sejours/sejours_ecran.dart';
import 'ecrans/tableau_de_bord/tableau_de_bord_ecran.dart';
import 'widgets/garde_connexion.dart';

/// Navigation de l'application Agent de terrain (P2-MOB-04) : connexion, tableau de bord,
/// mes séjours (check-in/check-out/état des lieux), mes missions de ménage, mes gains.
/// Hors périmètre ici (voir le rapport de livraison) : mode hors-ligne (P2-MOB-05),
/// notifications (P2-MOB-02), signature des builds (P2-MOB-06).
final GoRouter routeur = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(path: '/connexion', builder: (context, state) => const ConnexionEcran()),
    GoRoute(
      path: '/',
      builder: (context, state) => GardeConnexion(builder: (context, ref) => const TableauDeBordEcran()),
    ),
    GoRoute(
      path: '/sejours',
      builder: (context, state) => GardeConnexion(builder: (context, ref) => const SejoursEcran()),
    ),
    GoRoute(
      path: '/sejours/:id',
      builder: (context, state) => GardeConnexion(
        builder: (context, ref) => SejourDetailEcran(id: int.parse(state.pathParameters['id']!)),
      ),
    ),
    GoRoute(
      path: '/missions',
      builder: (context, state) => GardeConnexion(builder: (context, ref) => const MissionsEcran()),
    ),
    GoRoute(
      path: '/gains',
      builder: (context, state) => GardeConnexion(builder: (context, ref) => const GainsEcran()),
    ),
  ],
);
