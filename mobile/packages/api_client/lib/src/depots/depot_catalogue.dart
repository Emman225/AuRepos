import '../client_api.dart';
import '../modeles/donnees_accueil.dart';
import '../modeles/logement_fiche.dart';
import '../modeles/resultats_recherche.dart';

/// Catalogue public — mêmes routes que `web/src/features/site-public/api.ts` :
/// accueil, recherche et fiche logement. La disponibilité par mois et les
/// référentiels (communes, quartiers, équipements) restent hors périmètre de
/// cette tranche.
final class DepotCatalogue {
  DepotCatalogue(this._api);

  final ClientApi _api;

  Future<DonneesAccueil> accueil() =>
      _api.lire('/accueil', decoder: (data) => DonneesAccueil.depuisJson(data! as Map<String, dynamic>));

  Future<ResultatsRecherche> recherche(CriteresRecherche criteres) => _api.lire(
    '/catalogue/recherche',
    parametres: criteres.versParametres(),
    decoder: (data) => ResultatsRecherche.depuisJson(data! as Map<String, dynamic>),
  );

  Future<LogementFiche> ficheLogement(String reference) => _api.lire(
    '/catalogue/logements/$reference',
    decoder: (data) => LogementFiche.depuisJson(data! as Map<String, dynamic>),
  );
}
