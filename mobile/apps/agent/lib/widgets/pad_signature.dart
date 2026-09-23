import 'dart:convert';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:residences_ui/residences_ui.dart';

import '../l10n/app_localizations.dart';

/// Signature capturée à l'écran (état des lieux, CdC § 6.1) — équivalent Flutter du
/// `<canvas>` de `web/src/shared/composants/SignaturePad.tsx` : un simple
/// `CustomPainter` + `GestureDetector`, pas de lissage ni de pression, la signature n'a
/// qu'une valeur de preuve. La sortie est un PNG encodé en base64 avec le même préfixe
/// `data:image/png;base64,` que `canvas.toDataURL()` côté web, pour que
/// `EtatsDesLieux::signer()` reçoive exactement la même forme des deux applications.
class PadSignature extends StatefulWidget {
  const PadSignature({super.key, required this.onValider, this.enCours = false});

  final ValueChanged<String> onValider;
  final bool enCours;

  @override
  State<PadSignature> createState() => _PadSignatureState();
}

class _PadSignatureState extends State<PadSignature> {
  final _cleToile = GlobalKey();
  final List<List<Offset>> _traits = [];

  bool get _aDessine => _traits.isNotEmpty && _traits.any((t) => t.isNotEmpty);

  void _demarrer(DragStartDetails d) => setState(() => _traits.add([d.localPosition]));

  void _dessiner(DragUpdateDetails d) => setState(() => _traits.last.add(d.localPosition));

  void _effacer() => setState(_traits.clear);

  Future<void> _valider() async {
    final limite = _cleToile.currentContext?.findRenderObject();
    if (limite is! RenderRepaintBoundary) return;

    final image = await limite.toImage(pixelRatio: 2);
    final octets = await image.toByteData(format: ui.ImageByteFormat.png);
    if (octets == null) return;

    final base64 = base64Encode(octets.buffer.asUint8List());
    widget.onValider('data:image/png;base64,$base64');
  }

  @override
  Widget build(BuildContext context) {
    final t = Libelles.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        RepaintBoundary(
          key: _cleToile,
          child: GestureDetector(
            key: const ValueKey('zone-signature'),
            onPanStart: _demarrer,
            onPanUpdate: _dessiner,
            child: Container(
              height: 160,
              decoration: BoxDecoration(
                color: Couleurs.blanc,
                border: Border.all(color: Couleurs.bordure),
                borderRadius: BorderRadius.circular(8),
              ),
              child: CustomPaint(painter: _PeintreSignature(_traits), size: Size.infinite),
            ),
          ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            TextButton(onPressed: _aDessine ? _effacer : null, child: Text(t.etatsDesLieuxEffacerSignature)),
            const Spacer(),
            FilledButton(
              style: ThemeResidences.boutonEnLigne,
              onPressed: widget.enCours || !_aDessine ? null : _valider,
              child: widget.enCours
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Couleurs.blanc),
                    )
                  : Text(t.etatsDesLieuxValiderSignature),
            ),
          ],
        ),
        if (!_aDessine) ...[
          const SizedBox(height: 4),
          Text(
            t.etatsDesLieuxSignatureObligatoire,
            style: const TextStyle(color: Couleurs.texteDiscret, fontSize: 12),
          ),
        ],
      ],
    );
  }
}

class _PeintreSignature extends CustomPainter {
  const _PeintreSignature(this.traits);

  final List<List<Offset>> traits;

  @override
  void paint(Canvas canvas, Size size) {
    final peinture = Paint()
      ..color = Couleurs.texte
      ..strokeWidth = 2
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;

    for (final trait in traits) {
      for (var i = 0; i < trait.length - 1; i++) {
        canvas.drawLine(trait[i], trait[i + 1], peinture);
      }
    }
  }

  @override
  bool shouldRepaint(covariant _PeintreSignature oldDelegate) => oldDelegate.traits != traits;
}
