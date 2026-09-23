import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';

/// Inscription — `POST /auth/inscription`. Le compte créé reste en attente
/// de vérification par code (voir `web/src/features/auth/api.ts`) : cette
/// tranche ne construit pas l'écran de saisie du code, donc on ramène
/// simplement vers la connexion avec un message, sans connecter l'utilisateur.
class InscriptionEcran extends ConsumerStatefulWidget {
  const InscriptionEcran({super.key});

  @override
  ConsumerState<InscriptionEcran> createState() => _InscriptionEcranState();
}

class _InscriptionEcranState extends ConsumerState<InscriptionEcran> {
  final _cle = GlobalKey<FormState>();
  final _nom = TextEditingController();
  final _prenoms = TextEditingController();
  final _email = TextEditingController();
  final _telephone = TextEditingController();
  final _motDePasse = TextEditingController();
  final _motDePasseConfirmation = TextEditingController();
  bool _conditionsAcceptees = false;
  bool _enCours = false;
  String? _erreur;

  @override
  void dispose() {
    _nom.dispose();
    _prenoms.dispose();
    _email.dispose();
    _telephone.dispose();
    _motDePasse.dispose();
    _motDePasseConfirmation.dispose();
    super.dispose();
  }

  Future<void> _creerLeCompte() async {
    final t = Libelles.of(context);
    if (!(_cle.currentState?.validate() ?? false)) return;

    if (_motDePasse.text != _motDePasseConfirmation.text) {
      setState(() => _erreur = t.motsDePasseDifferents);
      return;
    }
    if (!_conditionsAcceptees) {
      setState(() => _erreur = t.conditionsObligatoires);
      return;
    }

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      await ref.read(depotAuthProvider).inscription(
        nom: _nom.text.trim(),
        prenoms: _prenoms.text.trim(),
        email: _email.text.trim(),
        telephone: _telephone.text.trim().isEmpty ? null : _telephone.text.trim(),
        motDePasse: _motDePasse.text,
        motDePasseConfirmation: _motDePasseConfirmation.text,
        conditionsAcceptees: _conditionsAcceptees,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t.inscriptionSucces)));
      context.go('/connexion');
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
      appBar: AppBar(title: Text(t.inscriptionTitre)),
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
                    controller: _nom,
                    decoration: InputDecoration(labelText: t.champNom),
                    validator: (v) => (v == null || v.trim().isEmpty) ? t.champObligatoire : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _prenoms,
                    decoration: InputDecoration(labelText: t.champPrenoms),
                    validator: (v) => (v == null || v.trim().isEmpty) ? t.champObligatoire : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(labelText: t.champEmail),
                    validator: (v) => (v == null || v.trim().isEmpty) ? t.champObligatoire : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _telephone,
                    keyboardType: TextInputType.phone,
                    decoration: InputDecoration(labelText: t.champTelephone),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _motDePasse,
                    obscureText: true,
                    decoration: InputDecoration(labelText: t.champMotDePasse),
                    validator: (v) => (v == null || v.isEmpty) ? t.champObligatoire : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _motDePasseConfirmation,
                    obscureText: true,
                    decoration: InputDecoration(labelText: t.champMotDePasseConfirmation),
                    validator: (v) => (v == null || v.isEmpty) ? t.champObligatoire : null,
                  ),
                  const SizedBox(height: 8),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    value: _conditionsAcceptees,
                    onChanged: (v) => setState(() => _conditionsAcceptees = v ?? false),
                    title: Text(t.conditionsAcceptees, style: const TextStyle(fontSize: 14)),
                  ),
                  if (_erreur != null) ...[
                    const SizedBox(height: 8),
                    Text(_erreur!, style: const TextStyle(color: Couleurs.erreur)),
                  ],
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _enCours ? null : _creerLeCompte,
                    child: _enCours
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Couleurs.blanc),
                          )
                        : Text(t.boutonCreerCompte),
                  ),
                  const SizedBox(height: 16),
                  // Wrap plutôt que Row : voir la même correction dans connexion_ecran.dart.
                  Wrap(
                    alignment: WrapAlignment.center,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Text(t.dejaUnCompte),
                      TextButton(onPressed: () => context.go('/connexion'), child: Text(t.seConnecter)),
                    ],
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
