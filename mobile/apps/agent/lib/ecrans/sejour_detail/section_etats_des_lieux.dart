import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/pad_signature.dart';

final etatsDesLieuxProvider = FutureProvider.autoDispose.family<List<EtatDesLieux>, int>(
  (ref, sejourId) => ref.watch(depotAgentProvider).listerLesEtatsDesLieux(sejourId),
);

/// État des lieux d'entrée / de sortie (CdC § 6.3, P2-SEJ-02) — équivalent de
/// `web/src/features/agent-terrain/SectionEtatsDesLieux.tsx` : inventaire, photos, signature
/// écran. Le PDF (« voir le pdf » côté web) n'a pas d'écran ici : hors périmètre de cette
/// tranche, voir le rapport de livraison.
class SectionEtatsDesLieux extends ConsumerWidget {
  const SectionEtatsDesLieux({super.key, required this.sejourId});

  final int sejourId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Libelles.of(context);
    final etats = ref.watch(etatsDesLieuxProvider(sejourId));

    return etats.when(
      loading: () => const SizedBox.shrink(),
      error: (_, _) => const SizedBox.shrink(),
      data: (liste) {
        final aEntree = liste.any((e) => e.type == TypeEtatDesLieux.entree);
        final aSortie = liste.any((e) => e.type == TypeEtatDesLieux.sortie);

        return Card(
          margin: const EdgeInsets.only(bottom: 16),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(t.etatsDesLieuxTitre, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  children: [
                    if (!aEntree)
                      OutlinedButton(
                        onPressed: () => ref.read(depotAgentProvider).etablirUnEtatDesLieux(sejourId, TypeEtatDesLieux.entree).then(
                          (_) => ref.invalidate(etatsDesLieuxProvider(sejourId)),
                        ),
                        child: Text(t.etatsDesLieuxEtablirEntree),
                      ),
                    if (!aSortie)
                      OutlinedButton(
                        onPressed: () => ref.read(depotAgentProvider).etablirUnEtatDesLieux(sejourId, TypeEtatDesLieux.sortie).then(
                          (_) => ref.invalidate(etatsDesLieuxProvider(sejourId)),
                        ),
                        child: Text(t.etatsDesLieuxEtablirSortie),
                      ),
                  ],
                ),
                for (final etat in liste) ...[
                  const Divider(height: 32),
                  _CarteEtatDesLieux(sejourId: sejourId, etat: etat),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}

class _CarteEtatDesLieux extends ConsumerStatefulWidget {
  const _CarteEtatDesLieux({required this.sejourId, required this.etat});

  final int sejourId;
  final EtatDesLieux etat;

  @override
  ConsumerState<_CarteEtatDesLieux> createState() => _CarteEtatDesLieuxState();
}

class _CarteEtatDesLieuxState extends ConsumerState<_CarteEtatDesLieux> {
  final _libelle = TextEditingController();
  final _observation = TextEditingController();
  bool _ajoutEnCours = false;
  bool _signatureEnCours = false;

  @override
  void dispose() {
    _libelle.dispose();
    _observation.dispose();
    super.dispose();
  }

  void _invalider() => ref.invalidate(etatsDesLieuxProvider(widget.sejourId));

  Future<void> _ajouterLaLigne() async {
    setState(() => _ajoutEnCours = true);
    try {
      await ref.read(depotAgentProvider).ajouterUneLigneEtatDesLieux(
        widget.sejourId,
        widget.etat.id,
        _libelle.text.trim(),
        observation: _observation.text.trim().isEmpty ? null : _observation.text.trim(),
      );
      _libelle.clear();
      _observation.clear();
      _invalider();
    } on ErreurApi {
      // Message déjà visible ailleurs dans l'écran ; pas de blocage de la saisie.
    } finally {
      if (mounted) setState(() => _ajoutEnCours = false);
    }
  }

  Future<void> _ajouterUnePhoto(int ligneId) async {
    final chemin = await ref.read(selecteurDePhotoProvider).choisir();
    if (chemin == null) return;
    await ref.read(depotAgentProvider).ajouterUnePhotoDeLigne(widget.sejourId, widget.etat.id, ligneId, cheminFichier: chemin);
    _invalider();
  }

  Future<void> _signer(String signatureBase64) async {
    setState(() => _signatureEnCours = true);
    try {
      await ref.read(depotAgentProvider).signerUnEtatDesLieux(widget.sejourId, widget.etat.id, signatureBase64);
      _invalider();
    } finally {
      if (mounted) setState(() => _signatureEnCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Libelles.of(context);
    final etat = widget.etat;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(etat.typeLibelle, style: const TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(width: 8),
            Chip(
              label: Text(etat.signe ? t.etatsDesLieuxSigne : t.etatsDesLieuxNonSigne),
              visualDensity: VisualDensity.compact,
              backgroundColor: etat.signe ? Couleurs.succes.withValues(alpha: 0.12) : Couleurs.sableClair,
            ),
          ],
        ),
        if (etat.commentaireGeneral != null && etat.commentaireGeneral!.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(etat.commentaireGeneral!, style: const TextStyle(color: Couleurs.texteDiscret)),
        ],
        const SizedBox(height: 8),
        for (final ligne in etat.lignes) _LigneEtatDesLieuxVue(ligne: ligne, signe: etat.signe, onAjouterPhoto: () => _ajouterUnePhoto(ligne.id)),
        if (!etat.signe) ...[
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              SizedBox(
                width: 200,
                child: TextField(
                  controller: _libelle,
                  decoration: InputDecoration(labelText: t.etatsDesLieuxLibelle),
                  onChanged: (_) => setState(() {}),
                ),
              ),
              SizedBox(
                width: 220,
                child: TextField(controller: _observation, decoration: InputDecoration(labelText: t.etatsDesLieuxObservation)),
              ),
              FilledButton(
                onPressed: _ajoutEnCours || _libelle.text.trim().isEmpty ? null : _ajouterLaLigne,
                child: Text(t.etatsDesLieuxAjouterLigne),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(t.etatsDesLieuxSignature, style: const TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          PadSignature(enCours: _signatureEnCours, onValider: _signer),
        ],
      ],
    );
  }
}

class _LigneEtatDesLieuxVue extends StatelessWidget {
  const _LigneEtatDesLieuxVue({required this.ligne, required this.signe, required this.onAjouterPhoto});

  final LigneEtatDesLieux ligne;
  final bool signe;
  final VoidCallback onAjouterPhoto;

  @override
  Widget build(BuildContext context) {
    final t = Libelles.of(context);
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(border: Border.all(color: Couleurs.bordure), borderRadius: BorderRadius.circular(8)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(ligne.libelle, style: const TextStyle(fontWeight: FontWeight.w600)),
          if (ligne.observation != null && ligne.observation!.isNotEmpty)
            Padding(padding: const EdgeInsets.only(top: 2), child: Text(ligne.observation!)),
          const SizedBox(height: 6),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              for (final photo in ligne.photos)
                Chip(avatar: const Icon(Icons.attach_file, size: 16), label: Text(photo.nomOriginal), visualDensity: VisualDensity.compact),
              if (!signe)
                TextButton.icon(
                  onPressed: onAjouterPhoto,
                  icon: const Icon(Icons.camera_alt_outlined, size: 16),
                  label: Text(t.etatsDesLieuxAjouterPhoto),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
