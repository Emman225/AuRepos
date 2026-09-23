import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';

final occupantsProvider = FutureProvider.autoDispose.family<List<OccupantDeSejour>, int>(
  (ref, sejourId) => ref.watch(depotAgentProvider).listerLesOccupants(sejourId),
);

/// Fiche de police (CdC § 6.3, § 11) — équivalent de
/// `web/src/features/agent-terrain/SectionOccupants.tsx` : jamais le numéro de pièce, seulement
/// `pieceFournie` et la liste des photos déjà déposées.
class SectionOccupants extends ConsumerWidget {
  const SectionOccupants({super.key, required this.sejourId});

  final int sejourId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Libelles.of(context);
    final occupants = ref.watch(occupantsProvider(sejourId));

    return occupants.when(
      loading: () => const SizedBox.shrink(),
      error: (_, _) => const SizedBox.shrink(),
      data: (liste) {
        if (liste.isEmpty) return const SizedBox.shrink();

        return Card(
          margin: const EdgeInsets.only(bottom: 16),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(t.occupantsTitre, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                for (final occupant in liste) _LigneOccupant(sejourId: sejourId, occupant: occupant, t: t),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _LigneOccupant extends ConsumerStatefulWidget {
  const _LigneOccupant({required this.sejourId, required this.occupant, required this.t});

  final int sejourId;
  final OccupantDeSejour occupant;
  final Libelles t;

  @override
  ConsumerState<_LigneOccupant> createState() => _LigneOccupantState();
}

class _LigneOccupantState extends ConsumerState<_LigneOccupant> {
  bool _enCours = false;

  Future<void> _ajouterUnePhoto() async {
    final chemin = await ref.read(selecteurDePhotoProvider).choisir();
    if (chemin == null || !mounted) return;

    setState(() => _enCours = true);
    try {
      await ref.read(depotAgentProvider).televerserLaPieceDUnOccupant(widget.sejourId, widget.occupant.id, cheminFichier: chemin);
      ref.invalidate(occupantsProvider(widget.sejourId));
    } on ErreurApi {
      // L'échec du dépôt reste visible : le libellé « fournie » ne changera pas.
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = widget.t;
    final o = widget.occupant;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${o.nomComplet}${o.enfant ? ' (${t.occupantsEnfant})' : ''}', style: const TextStyle(fontWeight: FontWeight.w600)),
                if (o.typePiece != null) Text('${t.occupantsTypePiece} : ${o.typePiece}', style: const TextStyle(fontSize: 12)),
                if (o.telephone != null) Text('${t.occupantsTelephone} : ${o.telephone}', style: const TextStyle(fontSize: 12)),
                Text(
                  o.pieces.isEmpty ? t.occupantsAucunePiece : o.pieces.map((p) => p.nomOriginal).join(', '),
                  style: const TextStyle(fontSize: 12, color: Couleurs.texteDiscret),
                ),
              ],
            ),
          ),
          _enCours
              ? const SizedBox(height: 32, width: 32, child: Padding(padding: EdgeInsets.all(6), child: CircularProgressIndicator(strokeWidth: 2)))
              : TextButton.icon(
                  onPressed: _ajouterUnePhoto,
                  icon: const Icon(Icons.camera_alt_outlined, size: 18),
                  label: Text(t.occupantsTeleverserPiece),
                ),
        ],
      ),
    );
  }
}
