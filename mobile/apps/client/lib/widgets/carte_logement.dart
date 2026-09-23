import 'package:flutter/material.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import '../l10n/app_localizations.dart';

/// Carte d'un logement : photo, nom, quartier/commune, prix/nuit.
/// Utilisée sur l'accueil (mises en avant) et dans les résultats de recherche
/// — même gabarit dans les deux écrans pour que la liste soit reconnaissable.
class CarteLogement extends StatelessWidget {
  const CarteLogement({super.key, required this.logement, required this.onTap});

  final VignetteLogement logement;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = Libelles.of(context);
    final prix = logement.prixParNuit;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 16 / 10,
              child: _PhotoLogement(url: logement.photo),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    logement.nom,
                    style: Theme.of(context).textTheme.titleMedium,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${logement.lieu.quartier}, ${logement.lieu.commune}',
                    style: const TextStyle(color: Couleurs.texteDiscret, fontSize: 13),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        prix != null ? t.prixParNuit(Formats.montant(prix, devise: 'FCFA')) : t.prixSurDemande,
                        style: const TextStyle(color: Couleurs.bleuNuit, fontWeight: FontWeight.w700),
                      ),
                      if (logement.noteMoyenne != null)
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.star, size: 16, color: Couleurs.alerte),
                            const SizedBox(width: 2),
                            Text(logement.noteMoyenne!.toStringAsFixed(1)),
                          ],
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

class _PhotoLogement extends StatelessWidget {
  const _PhotoLogement({required this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    final adresse = url;
    if (adresse == null || adresse.isEmpty) return const _PhotoIndisponible();

    return Image.network(
      adresse,
      fit: BoxFit.cover,
      width: double.infinity,
      errorBuilder: (context, error, stackTrace) => const _PhotoIndisponible(),
      loadingBuilder: (context, child, progres) {
        if (progres == null) return child;
        return const ColoredBox(color: Couleurs.sableClair, child: Center(child: CircularProgressIndicator()));
      },
    );
  }
}

class _PhotoIndisponible extends StatelessWidget {
  const _PhotoIndisponible();

  @override
  Widget build(BuildContext context) =>
      const ColoredBox(color: Couleurs.sableClair, child: Center(child: Icon(Icons.home_outlined, color: Couleurs.sable, size: 32)));
}
