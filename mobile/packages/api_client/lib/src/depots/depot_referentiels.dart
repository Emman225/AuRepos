import '../client_api.dart';
import '../modeles/commune_choix.dart';

/// Listes de référence pour les filtres. Seules les communes sont exposées
/// ici : la recherche mobile de cette tranche ne propose pas encore le choix
/// du quartier, du type de logement ni des équipements (voir la fiche
/// logement pour l'affichage de ces informations une fois le résultat ouvert).
final class DepotReferentiels {
  DepotReferentiels(this._api);

  final ClientApi _api;

  Future<List<CommuneChoix>> communes() => _api.lire(
    '/referentiels/communes',
    decoder: (data) => (data! as List<dynamic>).map((e) => CommuneChoix.depuisJson(e as Map<String, dynamic>)).toList(),
  );
}
