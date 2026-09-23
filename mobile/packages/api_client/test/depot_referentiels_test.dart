import 'package:flutter_test/flutter_test.dart';
import 'package:residences_api_client/residences_api_client.dart';

import 'aide/faux_serveur.dart';

void main() {
  test('communes décode la liste id/nom', () async {
    final serveur = FauxServeur(200, {
      'success': true,
      'message': '',
      'data': [
        {'id': 1, 'nom': 'Cocody'},
        {'id': 2, 'nom': 'Marcory'},
      ],
      'errors': null,
    });

    final communes = await DepotReferentiels(clientDeTest(serveur)).communes();

    expect(communes, hasLength(2));
    expect(communes.first.nom, 'Cocody');
    expect(serveur.derniere!.path, '/referentiels/communes');
  });
}
