import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';

/// Connexion — `POST /auth/connexion`, réservée aux comptes `agent_terrain` (CdC § 6.3).
///
/// N'importe quel compte peut s'authentifier côté serveur : le tri se fait ici, une fois le
/// profil de l'utilisateur connu. Un profil différent voit son jeton ignoré (jamais rangé
/// dans le coffre, jamais posé dans [sessionProvider]) et un message clair — pas de
/// redirection ni d'accès, même partiel, à l'espace agent.
class ConnexionEcran extends ConsumerStatefulWidget {
  const ConnexionEcran({super.key});

  @override
  ConsumerState<ConnexionEcran> createState() => _ConnexionEcranState();
}

class _ConnexionEcranState extends ConsumerState<ConnexionEcran> {
  final _cle = GlobalKey<FormState>();
  final _identifiant = TextEditingController();
  final _motDePasse = TextEditingController();
  bool _enCours = false;
  String? _erreur;

  @override
  void dispose() {
    _identifiant.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  Future<void> _seConnecter() async {
    final t = Libelles.of(context);
    if (!(_cle.currentState?.validate() ?? false)) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      final session = await ref.read(depotAuthProvider).connexion(
        identifiant: _identifiant.text.trim(),
        motDePasse: _motDePasse.text,
      );

      if (session.utilisateur.profil != profilAgentTerrain) {
        setState(() => _erreur = t.erreurProfilInvalide);
        return;
      }

      await ref.read(depotDeSessionProvider).ecrireJeton(session.jeton);
      ref.read(sessionProvider.notifier).definir(session.utilisateur);
      if (mounted) context.go('/');
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

    return Scaffold(
      appBar: AppBar(title: Text(t.connexionTitre)),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Form(
              key: _cle,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextFormField(
                    controller: _identifiant,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(labelText: t.champIdentifiant),
                    validator: (v) => (v == null || v.trim().isEmpty) ? t.champObligatoire : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _motDePasse,
                    obscureText: true,
                    decoration: InputDecoration(labelText: t.champMotDePasse),
                    validator: (v) => (v == null || v.isEmpty) ? t.champObligatoire : null,
                    onFieldSubmitted: (_) => _seConnecter(),
                  ),
                  if (_erreur != null) ...[
                    const SizedBox(height: 16),
                    Text(_erreur!, style: const TextStyle(color: Couleurs.erreur)),
                  ],
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _enCours ? null : _seConnecter,
                    child: _enCours
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Couleurs.blanc),
                          )
                        : Text(t.boutonSeConnecter),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
