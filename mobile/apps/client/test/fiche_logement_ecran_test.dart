import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_client/ecrans/fiche_logement/fiche_logement_ecran.dart';
import 'package:residences_client/l10n/app_localizations.dart';
import 'package:residences_ui/residences_ui.dart';

const _fiche = LogementFiche(
  reference: 'LOG-1',
  nom: 'Studio Cocody',
  resume: 'Studio calme, proche de tout.',
  type: TypeLogementRef(code: 'studio', nom: 'Studio'),
  residence: ResidenceRef(nom: 'Résidence Awa', slug: 'residence-awa'),
  lieu: Lieu(commune: 'Cocody', quartier: 'Angré'),
  nombrePieces: 1,
  nombreChambres: 1,
  nombreLits: 1,
  nombreSallesDeBain: 1,
  capaciteDeBase: 2,
  capaciteMaximale: 2,
  surfaceM2: 28,
  regles: ReglesLogement(fumeurAutorise: false, animauxAutorises: false, fetesAutorisees: false, texte: 'Pas de fêtes.'),
  heureArrivee: '14:00',
  heureDepart: '11:00',
  prixParNuit: 25000,
  devise: 'FCFA',
  caution: 50000,
  politiqueAnnulationLibelle: 'Annulation souple',
  equipements: [Equipement(nom: 'Wifi', portee: 'logement')],
  photos: [],
  noteMoyenne: 4.5,
  similaires: [],
);

Widget monter(Override etat) => ProviderScope(
  overrides: [etat],
  child: MaterialApp(
    theme: ThemeResidences.clair,
    locale: const Locale('fr'),
    localizationsDelegates: Libelles.localizationsDelegates,
    supportedLocales: Libelles.supportedLocales,
    home: const FicheLogementEcran(reference: 'LOG-1'),
  ),
);

void main() {
  testWidgets('affiche les informations principales de la fiche', (tester) async {
    await tester.pumpWidget(monter(ficheLogementProvider('LOG-1').overrideWith((ref) async => _fiche)));
    await tester.pumpAndSettle();

    expect(find.text('Studio Cocody'), findsWidgets);
    expect(find.text('Angré, Cocody'), findsOneWidget);
    expect(find.text('Wifi'), findsOneWidget);
    expect(find.textContaining('nuit'), findsOneWidget);
    expect(find.text('Jusqu’à 2 personnes'), findsOneWidget);
  });

  testWidgets('le bouton Réserver est désactivé', (tester) async {
    await tester.pumpWidget(monter(ficheLogementProvider('LOG-1').overrideWith((ref) async => _fiche)));
    await tester.pumpAndSettle();

    final bouton = tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Réserver'));
    expect(bouton.onPressed, isNull);
  });

  testWidgets('affiche le message en clair quand le logement est introuvable', (tester) async {
    await tester.pumpWidget(
      monter(
        ficheLogementProvider('LOG-1').overrideWith(
          (ref) async => throw const ErreurApi('Ce logement est introuvable.', statut: 404),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Ce logement est introuvable.'), findsOneWidget);
  });
}
