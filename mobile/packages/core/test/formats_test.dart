import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:residences_core/residences_core.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  test('un montant s’écrit avec des milliers séparés et la devise', () {
    expect(Formats.montant(1250000), '1 250 000 F');
    expect(Formats.montant(0), '0 F');
  });

  test('un montant est tronqué, jamais arrondi vers le haut', () {
    expect(Formats.montant(999.99), '999 F');
  });

  test('une date de liste suit le format jj/mm/aaaa hh:mm:ss', () {
    expect(Formats.dateHeure(DateTime(2026, 9, 21, 8, 5, 3)), '21/09/2026 08:05:03');
  });

  test('le dépôt de session en mémoire écrit, lit puis efface le jeton', () async {
    final depot = DepotDeSessionEnMemoire();
    expect(await depot.lireJeton(), isNull);

    await depot.ecrireJeton('abc');
    expect(await depot.lireJeton(), 'abc');

    await depot.effacer();
    expect(await depot.lireJeton(), isNull);
  });
}
