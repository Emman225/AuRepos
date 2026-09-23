import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_core/residences_core.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';
import 'formulaire_check_in.dart';
import 'formulaire_check_out.dart';
import 'section_etats_des_lieux.dart';
import 'section_occupants.dart';

final sejourDetailProvider = FutureProvider.autoDispose.family<SejourAgent, int>(
  (ref, id) => ref.watch(depotAgentProvider).afficherUnSejour(id),
);

/// Détail d'un séjour (CdC § 6.3, P2-MOB-04) — équivalent de
/// `web/src/features/agent-terrain/PageDetailSejour.tsx` : check-in, fiche de police, état
/// des lieux, check-out. Ni la confirmation de réservation ni la prolongation : hors du
/// périmètre de `routes/api_v1/agent.php`, réservées au back office.
class SejourDetailEcran extends ConsumerWidget {
  const SejourDetailEcran({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Libelles.of(context);
    final sejour = ref.watch(sejourDetailProvider(id));

    return Scaffold(
      appBar: AppBar(
        title: Text(sejour.valueOrNull?.reference ?? ''),
        leading: BackButton(onPressed: () => context.canPop() ? context.pop() : context.go('/sejours')),
      ),
      body: sejour.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (erreur, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              erreur is ErreurApi ? erreur.message : t.sejourIntrouvable,
              style: const TextStyle(color: Couleurs.erreur),
              textAlign: TextAlign.center,
            ),
          ),
        ),
        data: (s) => _CorpsDetail(sejour: s, t: t),
      ),
    );
  }
}

class _CorpsDetail extends ConsumerWidget {
  const _CorpsDetail({required this.sejour, required this.t});

  final SejourAgent sejour;
  final Libelles t;

  Future<void> _checkIn(BuildContext context, WidgetRef ref) async {
    final misAJour = await ouvrirFormulaireCheckIn(context, sejour);
    if (misAJour != null) ref.invalidate(sejourDetailProvider(sejour.id));
  }

  Future<void> _checkOut(BuildContext context, WidgetRef ref) async {
    final misAJour = await ouvrirFormulaireCheckOut(context, sejour);
    if (misAJour != null) ref.invalidate(sejourDetailProvider(sejour.id));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = sejour;
    final occupantsTexte =
        '${t.detailOccupantsAdultes(s.adultes)}${s.enfants > 0 ? t.detailEtEnfants(s.enfants) : ''}';

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(sejourDetailProvider(s.id)),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(s.reference, style: Theme.of(context).textTheme.titleLarge),
                        const SizedBox(width: 8),
                        Chip(label: Text(s.etatLibelle), visualDensity: VisualDensity.compact),
                      ],
                    ),
                    Text('${s.logement.nom} — ${s.logement.residence}', style: const TextStyle(color: Couleurs.texteDiscret)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(t.detailNetAPayer, style: const TextStyle(fontSize: 12, color: Couleurs.texteDiscret)),
                  Text(Formats.montant(s.netAPayer), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18, color: Couleurs.bleuNuit)),
                ],
              ),
            ],
          ),
          if (s.noShowLe != null) ...[
            const SizedBox(height: 16),
            _Alerte(couleur: Couleurs.alerte, texte: t.detailNoShow(s.noShowLe!)),
          ],
          if (s.cautionRetenue != null && s.cautionRetenue! > 0) ...[
            const SizedBox(height: 16),
            _Alerte(
              couleur: Couleurs.information,
              texte: t.detailCautionRetenueInfo(Formats.montant(s.cautionRetenue!)),
              sousTexte: s.cautionRetenueMotif,
            ),
          ],
          if (s.estConfirme || s.estArrive) ...[
            const SizedBox(height: 20),
            if (s.estConfirme) FilledButton(onPressed: () => _checkIn(context, ref), child: Text(t.checkInAction)),
            if (s.estArrive) FilledButton(onPressed: () => _checkOut(context, ref), child: Text(t.checkOutAction)),
          ],
          const SizedBox(height: 24),
          Text(t.detailSejour, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          _Fiche(
            lignes: [
              ('${t.sejoursArrivee} → ${t.sejoursDepart}', '${Formats.date(DateTime.parse(s.arrivee))} → ${Formats.date(DateTime.parse(s.depart))} (${t.detailNuits(s.nombreDeNuits)})'),
              (t.detailOccupants, occupantsTexte),
              if (s.client != null) (t.detailClient, '${s.client!.nom}${s.client!.telephone != null ? ' — ${s.client!.telephone}' : ''}'),
            ],
          ),
          const SizedBox(height: 24),
          Text(t.detailPaiement, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          _Fiche(
            lignes: [
              (t.detailEncaisse, Formats.montant(s.reglement.encaisse)),
              (t.detailResteDu, Formats.montant(s.reglement.resteDu)),
              (t.detailCaution, Formats.montant(s.caution)),
              if (s.cautionRetenue != null) (t.detailCautionRetenue, Formats.montant(s.cautionRetenue!)),
            ],
          ),
          const SizedBox(height: 24),
          if (s.etat != 'demande' && s.etat != 'annule') ...[
            SectionOccupants(sejourId: s.id),
            SectionEtatsDesLieux(sejourId: s.id),
          ],
        ],
      ),
    );
  }
}

class _Fiche extends StatelessWidget {
  const _Fiche({required this.lignes});

  final List<(String, String)> lignes;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(border: Border.all(color: Couleurs.bordure), borderRadius: BorderRadius.circular(8)),
      child: Column(
        children: [
          for (final (libelle, valeur) in lignes)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Couleurs.bordure))),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(libelle, style: const TextStyle(color: Couleurs.texteDiscret, fontSize: 13)),
                  Flexible(child: Text(valeur, textAlign: TextAlign.right)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _Alerte extends StatelessWidget {
  const _Alerte({required this.couleur, required this.texte, this.sousTexte});

  final Color couleur;
  final String texte;
  final String? sousTexte;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: couleur.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(texte, style: TextStyle(color: couleur, fontWeight: FontWeight.w600)),
          if (sousTexte != null) Text(sousTexte!, style: TextStyle(color: couleur)),
        ],
      ),
    );
  }
}
