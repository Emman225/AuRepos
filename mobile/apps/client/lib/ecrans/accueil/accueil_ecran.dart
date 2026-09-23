import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/carte_logement.dart';

final donneesAccueilProvider = FutureProvider.autoDispose<DonneesAccueil>(
  (ref) => ref.watch(depotCatalogueProvider).accueil(),
);

/// Accueil : logements mis en avant, tirés de `GET /accueil`.
///
/// Seule la vignette « logements mis en avant » est rendue dans cette
/// tranche (P2-MOB-03) ; le carrousel de bannières, les résidences les mieux
/// notées et les témoignages restent hors périmètre pour l'instant — voir le
/// rapport de livraison.
class AccueilEcran extends ConsumerWidget {
  const AccueilEcran({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Libelles.of(context);
    final donnees = ref.watch(donneesAccueilProvider);
    final session = ref.watch(sessionProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(t.marque),
        actions: [
          IconButton(
            tooltip: t.rechercheTitre,
            icon: const Icon(Icons.search),
            onPressed: () => context.push('/recherche'),
          ),
          session.when(
            data: (utilisateur) => utilisateur == null
                ? TextButton(
                    onPressed: () => context.push('/connexion'),
                    child: Text(t.connexion, style: const TextStyle(color: Couleurs.blanc)),
                  )
                : IconButton(
                    tooltip: t.deconnexion,
                    icon: const Icon(Icons.logout),
                    onPressed: () => ref.read(sessionProvider.notifier).deconnexion(),
                  ),
            loading: () => const SizedBox.shrink(),
            error: (_, _) => const SizedBox.shrink(),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(donneesAccueilProvider),
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(t.accueilTitre, style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 8),
            Text(t.accueilSousTitre, style: const TextStyle(color: Couleurs.texteDiscret, fontSize: 16)),
            const SizedBox(height: 24),
            Text(t.accueilLogementsMisEnAvant, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            donnees.when(
              loading: () => const Padding(
                padding: EdgeInsets.symmetric(vertical: 40),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (erreur, _) => _ErreurAccueil(erreur: erreur, t: t, ref: ref),
              data: (d) => d.misesEnAvant.isEmpty
                  ? Padding(padding: const EdgeInsets.symmetric(vertical: 24), child: Text(t.accueilAucunLogement))
                  : GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: d.misesEnAvant.length,
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        childAspectRatio: 0.72,
                      ),
                      itemBuilder: (context, index) {
                        final logement = d.misesEnAvant[index];
                        return CarteLogement(
                          logement: logement,
                          onTap: () => context.push('/logements/${logement.reference}'),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErreurAccueil extends StatelessWidget {
  const _ErreurAccueil({required this.erreur, required this.t, required this.ref});

  final Object erreur;
  final Libelles t;
  final WidgetRef ref;

  @override
  Widget build(BuildContext context) {
    final message = erreur is ErreurApi ? (erreur as ErreurApi).message : t.erreurGenerique;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(message, style: const TextStyle(color: Couleurs.erreur)),
        TextButton(onPressed: () => ref.invalidate(donneesAccueilProvider), child: Text(t.reessayer)),
      ],
    );
  }
}
