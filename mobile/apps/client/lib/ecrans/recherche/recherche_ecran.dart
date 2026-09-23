import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:residences_api_client/residences_api_client.dart';
import 'package:residences_ui/residences_ui.dart';

import '../../fournisseurs.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/carte_logement.dart';

final communesProvider = FutureProvider.autoDispose<List<CommuneChoix>>(
  (ref) => ref.watch(depotReferentielsProvider).communes(),
);

/// Critères actuellement soumis : `null` tant que l'utilisateur n'a pas
/// encore lancé de recherche (aucun appel réseau au premier affichage).
final criteresSoumisProvider = StateProvider.autoDispose<CriteresRecherche?>((ref) => null);

final resultatsRechercheProvider = FutureProvider.autoDispose.family<ResultatsRecherche, CriteresRecherche>(
  (ref, criteres) => ref.watch(depotCatalogueProvider).recherche(criteres),
);

final _formatDate = DateFormat('yyyy-MM-dd');

/// Recherche — `GET /catalogue/recherche`, filtres commune + dates au
/// minimum (quartier, type de logement, équipements : hors périmètre de
/// cette tranche, voir le rapport de livraison).
class RechercheEcran extends ConsumerStatefulWidget {
  const RechercheEcran({super.key});

  @override
  ConsumerState<RechercheEcran> createState() => _RechercheEcranState();
}

class _RechercheEcranState extends ConsumerState<RechercheEcran> {
  int? _communeId;
  DateTime? _arrivee;
  DateTime? _depart;

  Future<void> _choisirDate({required bool arrivee}) async {
    final maintenant = DateTime.now();
    final choisie = await showDatePicker(
      context: context,
      initialDate: (arrivee ? _arrivee : _depart) ?? maintenant,
      firstDate: maintenant,
      lastDate: maintenant.add(const Duration(days: 730)),
    );
    if (choisie == null) return;
    setState(() {
      if (arrivee) {
        _arrivee = choisie;
      } else {
        _depart = choisie;
      }
    });
  }

  void _rechercher() {
    ref.read(criteresSoumisProvider.notifier).state = CriteresRecherche(
      communeId: _communeId,
      arrivee: _arrivee != null ? _formatDate.format(_arrivee!) : null,
      depart: _depart != null ? _formatDate.format(_depart!) : null,
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = Libelles.of(context);
    final communes = ref.watch(communesProvider);
    final criteres = ref.watch(criteresSoumisProvider);

    return Scaffold(
      appBar: AppBar(title: Text(t.rechercheTitre)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                communes.when(
                  loading: () => const LinearProgressIndicator(),
                  error: (_, _) => Text(t.erreurGenerique, style: const TextStyle(color: Couleurs.erreur)),
                  data: (liste) => DropdownButtonFormField<int?>(
                    initialValue: _communeId,
                    decoration: InputDecoration(labelText: t.champCommune),
                    items: [
                      DropdownMenuItem(value: null, child: Text(t.toutesCommunes)),
                      for (final commune in liste) DropdownMenuItem(value: commune.id, child: Text(commune.nom)),
                    ],
                    onChanged: (v) => setState(() => _communeId = v),
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => _choisirDate(arrivee: true),
                        child: Text(_arrivee == null ? t.champArrivee : _formatDate.format(_arrivee!)),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => _choisirDate(arrivee: false),
                        child: Text(_depart == null ? t.champDepart : _formatDate.format(_depart!)),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                FilledButton(onPressed: _rechercher, child: Text(t.boutonRechercher)),
              ],
            ),
          ),
          Expanded(
            child: criteres == null
                ? const SizedBox.shrink()
                : Consumer(
                    builder: (context, ref, _) {
                      final resultats = ref.watch(resultatsRechercheProvider(criteres));
                      return resultats.when(
                        loading: () => const Center(child: CircularProgressIndicator()),
                        error: (erreur, _) => Center(
                          child: Text(
                            erreur is ErreurApi ? erreur.message : t.erreurGenerique,
                            style: const TextStyle(color: Couleurs.erreur),
                          ),
                        ),
                        data: (r) => r.elements.isEmpty
                            ? Center(child: Text(t.rechercheAucunResultat))
                            : Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Padding(
                                    padding: const EdgeInsets.symmetric(horizontal: 16),
                                    child: Text(t.rechercheResultats(r.pagination.total)),
                                  ),
                                  Expanded(
                                    child: GridView.builder(
                                      padding: const EdgeInsets.all(16),
                                      itemCount: r.elements.length,
                                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                        crossAxisCount: 2,
                                        mainAxisSpacing: 12,
                                        crossAxisSpacing: 12,
                                        childAspectRatio: 0.72,
                                      ),
                                      itemBuilder: (context, index) {
                                        final logement = r.elements[index];
                                        return CarteLogement(
                                          logement: logement,
                                          onTap: () => context.push('/logements/${logement.reference}'),
                                        );
                                      },
                                    ),
                                  ),
                                ],
                              ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
