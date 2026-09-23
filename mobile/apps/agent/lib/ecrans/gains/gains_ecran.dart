import 'package:flutter/material.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../l10n/app_localizations.dart';

/// Mes gains — lecture seule. Contrairement au chauffeur ou au livreur (des partenaires,
/// `Domain/Partenaires`, avec un `ModeDeRemuneration` et une route `/chauffeur/gains` ou
/// `/livreur/gains`), l'agent de terrain n'est qu'un compte `User` de profil `agent_terrain` :
/// aucune notion de rémunération n'existe pour lui côté API à ce jour, ni même côté back
/// office web (`web/src/features/agent-terrain/` n'a pas de page de gains). Cet écran affiche
/// donc un état « bientôt disponible » plutôt que d'appeler une route qui n'existe pas.
class GainsEcran extends StatelessWidget {
  const GainsEcran({super.key});

  @override
  Widget build(BuildContext context) {
    final t = Libelles.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(t.gainsTitre)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.payments_outlined, size: 48, color: Couleurs.sable),
              const SizedBox(height: 16),
              Text(t.gainsBientotDisponible, textAlign: TextAlign.center, style: const TextStyle(color: Couleurs.texteDiscret)),
            ],
          ),
        ),
      ),
    );
  }
}
