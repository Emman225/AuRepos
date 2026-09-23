import 'package:flutter_test/flutter_test.dart';
import 'package:residences_api_client/residences_api_client.dart';

import 'aide/faux_serveur.dart';

void main() {
  test('accueil décode le carrousel et les logements mis en avant', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'data': {
        'carrousel': [
          {'id': 1, 'image': 'https://ex.ci/a.jpg', 'legende': 'Bienvenue', 'lien': null},
        ],
        'mises_en_avant': [
          {
            'reference': 'LOG-1',
            'nom': 'Studio Cocody',
            'residence': 'Résidence Awa',
            'resume': 'Studio calme',
            'lieu': {'commune': 'Cocody', 'quartier': 'Angré'},
            'capacite_maximale': 2,
            'prix_par_nuit': 25000,
            'photo': 'https://ex.ci/logement.jpg',
            'note_moyenne': 4.5,
          },
        ],
        'residences_mises_en_avant': [],
        'residences_mieux_notees': [],
        'bannieres': [],
        'temoignages': [],
      },
      'errors': null,
    });

    final donnees = await DepotCatalogue(clientDeTest(serveur)).accueil();

    expect(donnees.carrousel, hasLength(1));
    expect(donnees.misesEnAvant, hasLength(1));
    final logement = donnees.misesEnAvant.single;
    expect(logement.reference, 'LOG-1');
    expect(logement.lieu.commune, 'Cocody');
    expect(logement.prixParNuit, 25000);
  });

  test('recherche envoie les critères commune et dates puis décode la pagination', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'data': {
        'avec_dates': true,
        'arrivee': '2026-10-01',
        'depart': '2026-10-05',
        'elements': [],
        'pagination': {'page': 1, 'par_page': 12, 'total': 0, 'derniere_page': 1},
      },
      'errors': null,
    });

    final resultats = await DepotCatalogue(clientDeTest(serveur)).recherche(
      const CriteresRecherche(arrivee: '2026-10-01', depart: '2026-10-05', communeId: 3),
    );

    expect(resultats.avecDates, isTrue);
    expect(resultats.pagination.total, 0);
    expect(serveur.derniere!.path, '/catalogue/recherche');
    expect(serveur.derniere!.queryParameters, {'arrivee': '2026-10-01', 'depart': '2026-10-05', 'commune_id': 3});
  });

  test('ficheLogement appelle le bon chemin et décode les photos', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'data': {
        'reference': 'LOG-1',
        'nom': 'Studio Cocody',
        'resume': 'Studio calme',
        'type': {'code': 'studio', 'nom': 'Studio'},
        'residence': {'nom': 'Résidence Awa', 'slug': 'residence-awa', 'description': null},
        'lieu': {'commune': 'Cocody', 'quartier': 'Angré'},
        'nombre_pieces': 1,
        'nombre_chambres': 1,
        'nombre_lits': 1,
        'nombre_salles_de_bain': 1,
        'capacite_de_base': 2,
        'capacite_maximale': 2,
        'surface_m2': 28,
        'description': 'Un studio lumineux.',
        'regles': {'fumeur_autorise': false, 'animaux_autorises': false, 'fetes_autorisees': false, 'texte': null},
        'heure_arrivee': '14:00',
        'heure_depart': '11:00',
        'prix_par_nuit': 25000,
        'devise': 'XOF',
        'caution': 50000,
        'duree_minimale': 1,
        'duree_maximale': null,
        'politique_annulation': 'souple',
        'politique_annulation_libelle': 'Annulation souple',
        'equipements': [
          {'nom': 'Wifi', 'icone': 'wifi', 'portee': 'logement'},
        ],
        'photos': [
          {'url': 'https://ex.ci/1.jpg', 'url_vignette': 'https://ex.ci/1-vignette.jpg', 'legende': null, 'couverture': true},
        ],
        'tarifs_par_saison': [],
        'note_moyenne': 4.5,
        'avis': [],
        'similaires': [],
      },
      'errors': null,
    });

    final fiche = await DepotCatalogue(clientDeTest(serveur)).ficheLogement('LOG-1');

    expect(serveur.derniere!.path, '/catalogue/logements/LOG-1');
    expect(fiche.nom, 'Studio Cocody');
    expect(fiche.photos.single.couverture, isTrue);
    expect(fiche.equipements.single.nom, 'Wifi');
  });
}
