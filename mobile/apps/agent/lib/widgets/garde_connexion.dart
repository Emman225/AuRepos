import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../fournisseurs.dart';

/// Garde d'un écran réservé aux agents connectés — équivalent simplifié de
/// `web/src/features/auth/RequireProfil.tsx` : le profil est déjà vérifié dans
/// [SessionNotifier], donc ici on n'a plus qu'à distinguer trois états.
///
///   - en cours de résolution → un indicateur, rien de l'écran protégé ;
///   - personne connecté (jeton absent, invalide, ou profil refusé) → retour à la connexion ;
///   - connecté → l'écran demandé.
///
/// Un confort d'interface seulement : le serveur refuse de toute façon l'appel d'un profil
/// non autorisé.
class GardeConnexion extends ConsumerWidget {
  const GardeConnexion({super.key, required this.builder});

  final Widget Function(BuildContext context, WidgetRef ref) builder;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);

    return session.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (_, _) => const Scaffold(body: Center(child: CircularProgressIndicator())),
      data: (utilisateur) {
        if (utilisateur == null) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (context.mounted) context.go('/connexion');
          });
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }
        return builder(context, ref);
      },
    );
  }
}
