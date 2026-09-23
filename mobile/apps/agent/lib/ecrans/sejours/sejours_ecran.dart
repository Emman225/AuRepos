import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';

/// `GET /agent/sejours` — mêmes critères que `SejoursController::index()` côté serveur :
/// arrivées confirmées dues aujourd'hui, départs arrivés dus aujourd'hui. Partagé avec le
/// tableau de bord (mêmes compteurs que `PageTableauDeBord.tsx`).
final sejoursDuJourProvider = FutureProvider.autoDispose<List<SejourAgent>>(
  (ref) => ref.watch(depotAgentProvider).mesSejoursDuJour(),
);

/// Espace agent de terrain › Mes séjours du jour (CdC § 6.3) — équivalent de
/// `web/src/features/agent-terrain/PageSejours.tsx`.
class SejoursEcran extends ConsumerWidget {
  const SejoursEcran({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Libelles.of(context);
    final sejours = ref.watch(sejoursDuJourProvider);

    return Scaffold(
      appBar: AppBar(title: Text(t.sejoursTitre)),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(sejoursDuJourProvider),
        child: sejours.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (erreur, _) => _Erreur(erreur: erreur, t: t, onReessayer: () => ref.invalidate(sejoursDuJourProvider)),
          data: (liste) => liste.isEmpty
              ? ListView(
                  children: [
                    Padding(padding: const EdgeInsets.all(32), child: Center(child: Text(t.sejoursAucun))),
                  ],
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: liste.length,
                  separatorBuilder: (context, index) => const SizedBox(height: 12),
                  itemBuilder: (context, index) => _CarteSejour(sejour: liste[index], t: t),
                ),
        ),
      ),
    );
  }
}

class _CarteSejour extends StatelessWidget {
  const _CarteSejour({required this.sejour, required this.t});

  final SejourAgent sejour;
  final Libelles t;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => context.push('/sejours/${sejour.id}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(sejour.reference, style: Theme.of(context).textTheme.titleMedium),
                  Chip(label: Text(sejour.etatLibelle), visualDensity: VisualDensity.compact),
                ],
              ),
              const SizedBox(height: 4),
              Text(sejour.logement.nom, style: const TextStyle(fontWeight: FontWeight.w600)),
              if (sejour.client != null)
                Text(sejour.client!.nom, style: const TextStyle(color: Couleurs.texteDiscret)),
              const SizedBox(height: 8),
              Text(
                '${t.sejoursArrivee} : ${Formats.date(DateTime.parse(sejour.arrivee))}  •  '
                '${t.sejoursDepart} : ${Formats.date(DateTime.parse(sejour.depart))}',
                style: const TextStyle(fontSize: 13, color: Couleurs.texteDiscret),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Erreur extends StatelessWidget {
  const _Erreur({required this.erreur, required this.t, required this.onReessayer});

  final Object erreur;
  final Libelles t;
  final VoidCallback onReessayer;

  @override
  Widget build(BuildContext context) {
    final message = erreur is ErreurApi ? (erreur as ErreurApi).message : t.erreurGenerique;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, style: const TextStyle(color: Couleurs.erreur), textAlign: TextAlign.center),
            TextButton(onPressed: onReessayer, child: Text(t.reessayer)),
          ],
        ),
      ),
    );
  }
}
