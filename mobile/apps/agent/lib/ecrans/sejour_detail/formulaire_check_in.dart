import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';

/// Check-in (CdC § 6.3, § 11) : le code d'arrivée est SAISI par l'agent, jamais lu ni affiché —
/// équivalent de `web/src/features/agent-terrain/FormulaireCheckIn.tsx`, même unique champ
/// `code`.
Future<SejourAgent?> ouvrirFormulaireCheckIn(BuildContext context, SejourAgent sejour) =>
    showDialog<SejourAgent>(context: context, builder: (context) => _FormulaireCheckIn(sejour: sejour));

class _FormulaireCheckIn extends ConsumerStatefulWidget {
  const _FormulaireCheckIn({required this.sejour});

  final SejourAgent sejour;

  @override
  ConsumerState<_FormulaireCheckIn> createState() => _FormulaireCheckInState();
}

class _FormulaireCheckInState extends ConsumerState<_FormulaireCheckIn> {
  final _code = TextEditingController();
  bool _enCours = false;
  String? _erreur;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _confirmer() async {
    final t = Libelles.of(context);
    setState(() {
      _enCours = true;
      _erreur = null;
    });
    try {
      final sejour = await ref.read(depotAgentProvider).faireLeCheckIn(widget.sejour.id, _code.text.trim());
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

    return AlertDialog(
      title: Text(t.checkInTitre),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(t.checkInAide, style: const TextStyle(color: Couleurs.texteDiscret)),
          const SizedBox(height: 16),
          TextField(
            controller: _code,
            autofocus: true,
            decoration: InputDecoration(labelText: t.checkInCode),
            onChanged: (_) => setState(() {}),
          ),
          if (_erreur != null) ...[
            const SizedBox(height: 12),
            Text(_erreur!, style: const TextStyle(color: Couleurs.erreur)),
          ],
        ],
      ),
      actions: [
        TextButton(onPressed: _enCours ? null : () => Navigator.of(context).pop(), child: Text(t.annuler)),
        FilledButton(
          onPressed: _enCours || _code.text.trim().isEmpty ? null : _confirmer,
          child: _enCours
              ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Couleurs.blanc))
              : Text(t.checkInConfirmer),
        ),
      ],
    );
  }
}
