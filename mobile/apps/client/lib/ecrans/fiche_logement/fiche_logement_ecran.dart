import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';

final ficheLogementProvider = FutureProvider.autoDispose.family<LogementFiche, String>(
  (ref, reference) => ref.watch(depotCatalogueProvider).ficheLogement(reference),
);

/// Fiche logement — `GET /catalogue/logements/{reference}`.
///
/// Consultation seulement : ni le calendrier de disponibilité, ni le devis,
/// ni le paiement ne sont construits dans cette tranche (P2-MOB-03). Le
/// bouton « Réserver » est affiché désactivé pour situer où le tunnel de
/// réservation prendra place dans une prochaine tranche.
class FicheLogementEcran extends ConsumerWidget {
  const FicheLogementEcran({super.key, required this.reference});

  final String reference;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Libelles.of(context);
    final fiche = ref.watch(ficheLogementProvider(reference));

    return Scaffold(
      appBar: AppBar(title: Text(fiche.valueOrNull?.nom ?? '')),
      body: fiche.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (erreur, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              erreur is ErreurApi ? erreur.message : t.ficheIntrouvable,
              style: const TextStyle(color: Couleurs.erreur),
              textAlign: TextAlign.center,
            ),
          ),
        ),
        data: (logement) => ListView(
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            _CarrouselPhotos(photos: logement.photos),
            Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(logement.nom, style: Theme.of(context).textTheme.headlineMedium),
                  const SizedBox(height: 4),
                  Text(
                    '${logement.lieu.quartier}, ${logement.lieu.commune}',
                    style: const TextStyle(color: Couleurs.texteDiscret, fontSize: 15),
                  ),
                  const SizedBox(height: 12),
                  Text(logement.resume, style: Theme.of(context).textTheme.bodyMedium),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 16,
                    runSpacing: 8,
                    children: [
                      _Statistique(label: t.libelleChambres, valeur: '${logement.nombreChambres}'),
                      if (logement.nombreLits != null)
                        _Statistique(label: t.libelleLits, valeur: '${logement.nombreLits}'),
                      if (logement.nombreSallesDeBain != null)
                        _Statistique(label: t.libelleSallesDeBain, valeur: '${logement.nombreSallesDeBain}'),
                      if (logement.surfaceM2 != null)
                        _Statistique(label: t.libelleSurface, valeur: '${logement.surfaceM2} m²'),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(t.ficheCapacite(logement.capaciteMaximale)),
                  const Divider(height: 32),
                  if (logement.equipements.isNotEmpty) ...[
                    Text(t.ficheEquipements, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [for (final e in logement.equipements) Chip(label: Text(e.nom))],
                    ),
                    const Divider(height: 32),
                  ],
                  Text(t.ficheRegles, style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 8),
                  if (logement.regles.texte != null && logement.regles.texte!.isNotEmpty)
                    Text(logement.regles.texte!),
                  const SizedBox(height: 4),
                  Text('${t.libelleCaution} : ${Formats.montant(logement.caution, devise: 'FCFA')}'),
                  Text('${t.libelleAnnulation} : ${logement.politiqueAnnulationLibelle}'),
                  const Divider(height: 32),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(t.ficheTarif, style: Theme.of(context).textTheme.titleMedium),
                          Text(
                            t.prixParNuit(Formats.montant(logement.prixParNuit, devise: logement.devise)),
                            style: const TextStyle(color: Couleurs.bleuNuit, fontWeight: FontWeight.w700, fontSize: 18),
                          ),
                        ],
                      ),
                      // Largeur explicite : le thème donne aux boutons une largeur minimale
                      // infinie (`Size.fromHeight`, pensée pour un bouton plein écran dans une
                      // colonne étirée) ; à l'intérieur d'un Row sous un Tooltip, cette largeur
                      // infinie remonte jusqu'aux contraintes de layout et fait planter le rendu.
                      SizedBox(
                        width: 160,
                        child: Tooltip(
                          message: t.ficheBientotDisponible,
                          child: FilledButton(onPressed: null, child: Text(t.ficheReserver)),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Statistique extends StatelessWidget {
  const _Statistique({required this.label, required this.valeur});

  final String label;
  final String valeur;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(valeur, style: Theme.of(context).textTheme.titleMedium),
      Text(label, style: const TextStyle(color: Couleurs.texteDiscret, fontSize: 12)),
    ],
  );
}

class _CarrouselPhotos extends StatelessWidget {
  const _CarrouselPhotos({required this.photos});

  final List<PhotoLogement> photos;

  @override
  Widget build(BuildContext context) {
    if (photos.isEmpty) {
      return const AspectRatio(
        aspectRatio: 16 / 9,
        child: ColoredBox(color: Couleurs.sableClair, child: Icon(Icons.home_outlined, color: Couleurs.sable, size: 48)),
      );
    }

    return AspectRatio(
      aspectRatio: 16 / 9,
      child: PageView(
        children: [
          for (final photo in photos)
            Image.network(
              photo.url,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => const ColoredBox(color: Couleurs.sableClair),
            ),
        ],
      ),
    );
  }
}
