import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';

/// Filtre actuellement choisi ; `null` = tous les statuts.
final statutMissionFiltreProvider = StateProvider.autoDispose<StatutDeMission?>((ref) => null);

/// `GET /agent/missions` — seulement celles qui me sont affectées (`MissionsController::index()`).
final missionsProvider = FutureProvider.autoDispose.family<List<Mission>, StatutDeMission?>(
  (ref, statut) => ref.watch(depotAgentProvider).mesMissions(statut: statut),
);

/// Espace agent de terrain › Mes missions de ménage (CdC § 6.4, P2-MEN-01) — équivalent de
/// `web/src/features/agent-terrain/PageMissions.tsx` : démarrer / terminer, filtre par statut.
///
/// `Missions::terminer()` ne prend aujourd'hui qu'un commentaire libre (`{notes?}`) : pas
/// encore de check-list, de photos ni de champ « anomalie » côté serveur — voir le rapport
/// de livraison pour la version enrichie à venir.
class MissionsEcran extends ConsumerStatefulWidget {
  const MissionsEcran({super.key});

  @override
  ConsumerState<MissionsEcran> createState() => _MissionsEcranState();
}

class _MissionsEcranState extends ConsumerState<MissionsEcran> {
  final _notes = TextEditingController();
  String? _erreur;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  void _invalider() {
    ref.invalidate(missionsProvider);
  }

  Future<void> _demarrer(Mission mission) async {
    try {
      await ref.read(depotAgentProvider).demarrerUneMission(mission.id);
      _invalider();
    } on ErreurApi catch (e) {
      setState(() => _erreur = e.message);
    }
  }

  Future<void> _ouvrirTerminer(Mission mission) async {
    _notes.clear();
    final t = Libelles.of(context);
    final confirme = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t.missionsTerminerTitre),
        content: TextField(
          controller: _notes,
          maxLines: 3,
          decoration: InputDecoration(labelText: t.missionsNotes),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(t.annuler)),
          FilledButton(
            style: ThemeResidences.boutonEnLigne,
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(t.missionsTerminer),
          ),
        ],
      ),
    );

    if (confirme != true) return;

    try {
      await ref.read(depotAgentProvider).terminerUneMission(mission.id, notes: _notes.text.trim());
      _invalider();
    } on ErreurApi catch (e) {
      setState(() => _erreur = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Libelles.of(context);
    final statut = ref.watch(statutMissionFiltreProvider);
    final missions = ref.watch(missionsProvider(statut));

    return Scaffold(
      appBar: AppBar(title: Text(t.missionsTitre)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: DropdownButtonFormField<StatutDeMission?>(
              initialValue: statut,
              decoration: InputDecoration(labelText: t.missionsTousLesStatuts),
              items: [
                DropdownMenuItem(value: null, child: Text(t.missionsTousLesStatuts)),
                DropdownMenuItem(value: StatutDeMission.aFaire, child: Text(t.missionsStatutAFaire)),
                DropdownMenuItem(value: StatutDeMission.enCours, child: Text(t.missionsStatutEnCours)),
                DropdownMenuItem(value: StatutDeMission.faite, child: Text(t.missionsStatutFaite)),
              ],
              onChanged: (v) => ref.read(statutMissionFiltreProvider.notifier).state = v,
            ),
          ),
          if (_erreur != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(_erreur!, style: const TextStyle(color: Couleurs.erreur)),
            ),
          Expanded(
            child: missions.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (erreur, _) => Center(
                child: Text(
                  erreur is ErreurApi ? erreur.message : t.erreurGenerique,
                  style: const TextStyle(color: Couleurs.erreur),
                ),
              ),
              data: (liste) => liste.isEmpty
                  ? Center(child: Text(t.missionsAucune))
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: liste.length,
                      separatorBuilder: (context, index) => const SizedBox(height: 12),
                      itemBuilder: (context, index) => _CarteMission(
                        mission: liste[index],
                        t: t,
                        surDemarrer: () => _demarrer(liste[index]),
                        surTerminer: () => _ouvrirTerminer(liste[index]),
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CarteMission extends StatelessWidget {
  const _CarteMission({required this.mission, required this.t, required this.surDemarrer, required this.surTerminer});

  final Mission mission;
  final Libelles t;
  final VoidCallback surDemarrer;
  final VoidCallback surTerminer;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(mission.typeLibelle, style: Theme.of(context).textTheme.titleMedium),
                Chip(label: Text(mission.statutLibelle), visualDensity: VisualDensity.compact),
              ],
            ),
            const SizedBox(height: 4),
            Text(mission.logement?.nom ?? t.missionsSansLogement),
            if (mission.sejour != null)
              Text(mission.sejour!.reference, style: const TextStyle(color: Couleurs.texteDiscret, fontSize: 13)),
            const SizedBox(height: 4),
            Text(
              '${t.missionsEcheance} : ${Formats.dateHeure(DateTime.parse(mission.echeance))}',
              style: const TextStyle(fontSize: 13, color: Couleurs.texteDiscret),
            ),
            if (mission.statut == StatutDeMission.aFaire || mission.statut == StatutDeMission.enCours) ...[
              const SizedBox(height: 12),
              Align(
                alignment: Alignment.centerRight,
                child: mission.statut == StatutDeMission.aFaire
                    ? OutlinedButton(onPressed: surDemarrer, child: Text(t.missionsDemarrer))
                    : FilledButton(
                        style: ThemeResidences.boutonEnLigne,
                        onPressed: surTerminer,
                        child: Text(t.missionsTerminer),
                      ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
