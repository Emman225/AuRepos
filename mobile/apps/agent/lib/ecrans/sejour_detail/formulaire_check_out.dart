import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';

final _consommationsProvider = FutureProvider.autoDispose.family<ConsommationsDuSejour, int>(
  (ref, sejourId) => ref.watch(depotAgentProvider).afficherLesConsommations(sejourId),
);

/// Check-out (CdC § 6.3) : consommations facturées, décision manuelle sur la caution —
/// équivalent de `web/src/features/agent-terrain/FormulaireCheckOut.tsx`, mêmes champs
/// `caution_retenue` (obligatoire) et `motif` (obligatoire dès qu'une caution est retenue).
Future<SejourAgent?> ouvrirFormulaireCheckOut(BuildContext context, SejourAgent sejour) =>
    showDialog<SejourAgent>(context: context, builder: (context) => _FormulaireCheckOut(sejour: sejour));

class _FormulaireCheckOut extends ConsumerStatefulWidget {
  const _FormulaireCheckOut({required this.sejour});

  final SejourAgent sejour;

  @override
  ConsumerState<_FormulaireCheckOut> createState() => _FormulaireCheckOutState();
}

class _FormulaireCheckOutState extends ConsumerState<_FormulaireCheckOut> {
  final _cautionRetenue = TextEditingController(text: '0');
  final _motif = TextEditingController();
  bool _enCours = false;
  String? _erreur;

  @override
  void dispose() {
    _cautionRetenue.dispose();
    _motif.dispose();
    super.dispose();
  }

  int get _caution => int.tryParse(_cautionRetenue.text) ?? 0;
  bool get _motifManquant => _caution > 0 && _motif.text.trim().length < 5;

  Future<void> _confirmer() async {
    final t = Libelles.of(context);
    setState(() {
      _enCours = true;
      _erreur = null;
    });
    try {
      final sejour = await ref
          .read(depotAgentProvider)
          .faireLeCheckOut(widget.sejour.id, _caution, motif: _motif.text.trim().isEmpty ? null : _motif.text.trim());
      if (mounted) Navigator.of(context).pop(sejour);
    } on ErreurApi catch (e) {
      setState(() => _erreur = e.message);
    } catch (_) {
      setState(() => _erreur = t.erreurGenerique);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Libelles.of(context);
    final consommations = ref.watch(_consommationsProvider(widget.sejour.id));

    return AlertDialog(
      title: Text(t.checkOutTitre),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(t.checkOutConsommations, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            consommations.when(
              loading: () => const Center(child: Padding(padding: EdgeInsets.all(8), child: CircularProgressIndicator())),
              error: (_, _) => Text(t.erreurGenerique, style: const TextStyle(color: Couleurs.erreur)),
              data: (c) => c.total == 0
                  ? Text(t.checkOutAucuneConsommation, style: const TextStyle(color: Couleurs.texteDiscret))
                  : Column(
                      children: [
                        _LigneConsommation(libelle: t.checkOutHebergement, valeur: c.hebergement),
                        _LigneConsommation(libelle: t.checkOutRepas, valeur: c.repas),
                        _LigneConsommation(libelle: t.checkOutTransferts, valeur: c.transferts),
                        const Divider(),
                        _LigneConsommation(libelle: t.checkOutTotal, valeur: c.total, gras: true),
                      ],
                    ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _cautionRetenue,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: InputDecoration(labelText: t.checkOutCautionRetenue, suffixText: 'F'),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _motif,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: t.checkOutMotif,
                errorText: _motifManquant ? t.checkOutMotifObligatoire : null,
              ),
              onChanged: (_) => setState(() {}),
            ),
            if (_erreur != null) ...[
              const SizedBox(height: 12),
              Text(_erreur!, style: const TextStyle(color: Couleurs.erreur)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: _enCours ? null : () => Navigator.of(context).pop(), child: Text(t.annuler)),
        FilledButton(
          style: ThemeResidences.boutonEnLigne,
          onPressed: _enCours || _motifManquant ? null : _confirmer,
          child: _enCours
              ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Couleurs.blanc))
              : Text(t.checkOutConfirmer),
        ),
      ],
    );
  }
}

class _LigneConsommation extends StatelessWidget {
  const _LigneConsommation({required this.libelle, required this.valeur, this.gras = false});

  final String libelle;
  final num valeur;
  final bool gras;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(fontWeight: gras ? FontWeight.w700 : FontWeight.normal, fontSize: 13);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(libelle, style: style), Text(Formats.montant(valeur), style: style)],
      ),
    );
  }
}
