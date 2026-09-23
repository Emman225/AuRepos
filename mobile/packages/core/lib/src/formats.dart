import 'package:intl/intl.dart';

/// Mise en forme seulement. Aucun calcul de prix, de taxe ni de remise ne se
/// fait dans l'application : le serveur les arrête, l'application les affiche.
abstract final class Formats {
  static final _milliers = NumberFormat('#,##0', 'fr_FR');
  static final _dateHeure = DateFormat('dd/MM/yyyy HH:mm:ss');
  static final _date = DateFormat('dd/MM/yyyy');

  /// 1250000 → « 1 250 000 F »
  static String montant(num valeur, {String devise = 'F'}) {
    // intl sépare les milliers par une espace fine insécable ; on la garde
    // insécable pour que le montant ne se coupe jamais en fin de ligne.
    final chiffres = _milliers.format(valeur.truncate()).replaceAll(' ', ' ');
    return '$chiffres $devise';
  }

  /// Format des listes du cahier des charges : jj/mm/aaaa hh:mm:ss
  static String dateHeure(DateTime d) => _dateHeure.format(d);

  static String date(DateTime d) => _date.format(d);
}
