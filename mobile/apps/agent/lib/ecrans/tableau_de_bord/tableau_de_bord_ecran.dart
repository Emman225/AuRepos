import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';
import '../missions/missions_ecran.dart';
import '../sejours/sejours_ecran.dart';

/// Tableau de bord — équivalent de `web/src/features/agent-terrain/PageTableauDeBord.tsx` :
/// ma journée, dérivée des deux mêmes listes que les écrans « Mes séjours » et
/// « Mes missions » (`routes/api_v1/agent.php` n'expose pas de point d'agrégation dédié).
class TableauDeBordEcran extends ConsumerWidget {
  const TableauDeBordEcran({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Libelles.of(context);
    final session = ref.watch(sessionProvider);
    final sejours = ref.watch(sejoursDuJourProvider);
    final missions = ref.watch(missionsProvider(null));
    final utilisateur = session.valueOrNull;

    final arrivees = sejours.valueOrNull?.where((s) => s.estConfirme).length ?? 0;
    final departs = sejours.valueOrNull?.where((s) => s.estArrive).length ?? 0;
    final missionsAFaire = missions.valueOrNull?.where((m) => m.statut != StatutDeMission.faite).length ?? 0;
    final chargement = sejours.isLoading || missions.isLoading;

    return Scaffold(
      appBar: AppBar(
        title: Text(t.marque),
        actions: [
          IconButton(
            tooltip: t.deconnexion,
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(sessionProvider.notifier).deconnexion(),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(sejoursDuJourProvider);
          ref.invalidate(missionsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (utilisateur != null)
              Text(t.bienvenue(utilisateur.prenoms ?? utilisateur.nom), style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 20),
            if (chargement)
              const Padding(padding: EdgeInsets.symmetric(vertical: 24), child: Center(child: CircularProgressIndicator()))
            else
              Column(
                children: [
                  _CarteKpi(
                    icone: Icons.login,
                    libelle: t.kpiArrivees,
                    valeur: arrivees,
                    onTap: () => context.push('/sejours'),
                  ),
                  const SizedBox(height: 12),
                  _CarteKpi(
                    icone: Icons.logout,
                    libelle: t.kpiDeparts,
                    valeur: departs,
                    onTap: () => context.push('/sejours'),
                  ),
                  const SizedBox(height: 12),
                  _CarteKpi(
                    icone: Icons.cleaning_services,
                    libelle: t.kpiMissions,
                    valeur: missionsAFaire,
                    onTap: () => context.push('/missions'),
                  ),
                ],
              ),
            const SizedBox(height: 28),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                OutlinedButton.icon(
                  onPressed: () => context.push('/sejours'),
                  icon: const Icon(Icons.event_available),
                  label: Text(t.menuSejours),
                ),
                OutlinedButton.icon(
                  onPressed: () => context.push('/missions'),
                  icon: const Icon(Icons.cleaning_services_outlined),
                  label: Text(t.menuMissions),
                ),
                OutlinedButton.icon(
                  onPressed: () => context.push('/gains'),
                  icon: const Icon(Icons.payments_outlined),
                  label: Text(t.menuGains),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CarteKpi extends StatelessWidget {
  const _CarteKpi({required this.icone, required this.libelle, required this.valeur, required this.onTap});

  final IconData icone;
  final String libelle;
  final int valeur;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Icon(icone, color: valeur > 0 ? Couleurs.bleuNuit : Couleurs.texteDiscret, size: 28),
              const SizedBox(width: 16),
              Expanded(child: Text(libelle, style: const TextStyle(fontSize: 15))),
              Text('$valeur', style: Theme.of(context).textTheme.headlineSmall),
            ],
          ),
        ),
      ),
    );
  }
}
