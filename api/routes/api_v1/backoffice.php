<?php

use App\Http\Controllers\Api\V1\Backoffice\AgencesController;
use App\Http\Controllers\Api\V1\Backoffice\ApporteursController;
use App\Http\Controllers\Api\V1\Backoffice\ArticlesController;
use App\Http\Controllers\Api\V1\Backoffice\AuditController;
use App\Http\Controllers\Api\V1\Backoffice\AvancesController;
use App\Http\Controllers\Api\V1\Backoffice\AvisController;
use App\Http\Controllers\Api\V1\Backoffice\BannieresController;
use App\Http\Controllers\Api\V1\Backoffice\BaremesLivraisonRepasController;
use App\Http\Controllers\Api\V1\Backoffice\BaremesTransfertController;
use App\Http\Controllers\Api\V1\Backoffice\CaisseController;
use App\Http\Controllers\Api\V1\Backoffice\CalculSejourController;
use App\Http\Controllers\Api\V1\Backoffice\CalendrierController;
use App\Http\Controllers\Api\V1\Backoffice\CarrouselController;
use App\Http\Controllers\Api\V1\Backoffice\ChangementsController;
use App\Http\Controllers\Api\V1\Backoffice\ChauffeursController;
use App\Http\Controllers\Api\V1\Backoffice\ClientsController;
use App\Http\Controllers\Api\V1\Backoffice\CodePromoController;
use App\Http\Controllers\Api\V1\Backoffice\CommandesRepasController;
use App\Http\Controllers\Api\V1\Backoffice\ComptesATermeController;
use App\Http\Controllers\Api\V1\Backoffice\DemandesAnnulationController;
use App\Http\Controllers\Api\V1\Backoffice\DevisController;
use App\Http\Controllers\Api\V1\Backoffice\EtatsDesLieuxController;
use App\Http\Controllers\Api\V1\Backoffice\FacturesController;
use App\Http\Controllers\Api\V1\Backoffice\GrilleTarifaireController;
use App\Http\Controllers\Api\V1\Backoffice\LivreursController;
use App\Http\Controllers\Api\V1\Backoffice\LogementsController;
use App\Http\Controllers\Api\V1\Backoffice\MissionsController;
use App\Http\Controllers\Api\V1\Backoffice\NewsletterController;
use App\Http\Controllers\Api\V1\Backoffice\NotificationsController;
use App\Http\Controllers\Api\V1\Backoffice\ParametresController;
use App\Http\Controllers\Api\V1\Backoffice\PersonnelController;
use App\Http\Controllers\Api\V1\Backoffice\PhotosLogementController;
use App\Http\Controllers\Api\V1\Backoffice\PiecesClientController;
use App\Http\Controllers\Api\V1\Backoffice\PiecesProprietaireController;
use App\Http\Controllers\Api\V1\Backoffice\PlanningController;
use App\Http\Controllers\Api\V1\Backoffice\PrixLogementController;
use App\Http\Controllers\Api\V1\Backoffice\PrixNegocieController;
use App\Http\Controllers\Api\V1\Backoffice\ProduitsRepasController;
use App\Http\Controllers\Api\V1\Backoffice\ProprietairesController;
use App\Http\Controllers\Api\V1\Backoffice\PublicationLogementController;
use App\Http\Controllers\Api\V1\Backoffice\ReclamationsController;
use App\Http\Controllers\Api\V1\Backoffice\ResidencesController;
use App\Http\Controllers\Api\V1\Backoffice\RestaurateursController;
use App\Http\Controllers\Api\V1\Backoffice\SejoursController;
use App\Http\Controllers\Api\V1\Backoffice\TableauDeBordController;
use App\Http\Controllers\Api\V1\Backoffice\TicketsAssistanceController;
use App\Http\Controllers\Api\V1\Backoffice\TransfertsController;
use App\Http\Controllers\Api\V1\Backoffice\VehiculesController;
use App\Http\Controllers\Api\V1\ReferentielsController;
use Illuminate\Support\Facades\Route;

// Back office. Le cloisonnement se pose sur le groupe : aucune route d'ici
// n'est atteignable sans jeton valide, compte actif ET profil autorisé.

Route::prefix('backoffice')->name('backoffice.')->middleware('connecte')->group(function (): void {

    // Exploitation : administrateurs et gestionnaires (CdC § 6).
    Route::middleware('profil:super_administrateur,administrateur,gestionnaire')->group(function (): void {

        Route::get('tableau-de-bord', [TableauDeBordController::class, 'index'])->name('tableau-de-bord');

        // Planning (grille logements × jours) : agrégat en lecture seule des logements et de
        // leurs séjours sur une période, pour l'écran back-office React à venir.
        Route::get('planning', [PlanningController::class, 'index'])->name('planning');

        Route::prefix('referentiels/{slug}')->name('referentiels.')->group(function (): void {
            Route::get('/', [ReferentielsController::class, 'index'])->name('index');
            // Avant {id} : « export » ne doit jamais être pris pour un identifiant.
            Route::get('export', [ReferentielsController::class, 'exporter'])->name('exporter');
            Route::post('/', [ReferentielsController::class, 'creer'])->name('creer');
            Route::put('{id}', [ReferentielsController::class, 'modifier'])->whereNumber('id')->name('modifier');
            Route::delete('{id}', [ReferentielsController::class, 'supprimer'])->whereNumber('id')->name('supprimer');
        });

        // Caisse — saisie et consultation : administrateurs et caissiers (gestionnaires rattachés à une agence).
        Route::prefix('caisse')->name('caisse.')->group(function (): void {
            Route::get('reglements', [CaisseController::class, 'index'])->name('reglements.index');
            // Avant {reglement} : « export » ne doit jamais être pris pour un identifiant de règlement.
            Route::get('reglements/export', [CaisseController::class, 'exporter'])->name('reglements.exporter');
            Route::get('clients/{client}/affaires', [CaisseController::class, 'affairesDuClient'])->name('clients.affaires');
            Route::post('encaissements', [CaisseController::class, 'encaisser'])->name('encaissements.saisir');
            Route::get('reglements/{reglement}/recu', [CaisseController::class, 'recu'])->name('reglements.recu');
            Route::post('reglements/{reglement}/recu/renvoi', [CaisseController::class, 'renvoyerLeRecu'])->middleware('throttle:6,1')->name('reglements.recu.renvoyer');

            // Guichet des avances : dépôt sans réservation, situation du compte d'un client.
            Route::get('clients/{client}/avances', [AvancesController::class, 'situation'])->name('clients.avances');
            Route::post('avances', [AvancesController::class, 'deposer'])->name('avances.deposer');
        });

        // Chiffrage d'un séjour par la réception : même moteur que le site public.
        Route::post('sejours/calcul', CalculSejourController::class)->name('sejours.calcul');

        // Réservations : liste, fiche, confirmation, renvoi du code d'arrivée (que le personnel ne voit jamais).
        // cloisonner.residence : un gestionnaire n'agit que sur les séjours de SES résidences (CdC § 9.5).
        Route::get('sejours', [SejoursController::class, 'index'])->name('sejours.index');
        Route::get('sejours/export', [SejoursController::class, 'exporter'])->name('sejours.exporter');
        // Réservation manuelle : téléphone, walk-in, canal externe (CdC § 6.1). Le contrôleur
        // vérifie lui-même le périmètre du gestionnaire (le logement vient du corps, pas de l'URL).
        Route::post('sejours', [SejoursController::class, 'creer'])->middleware('throttle:20,1')->name('sejours.creer');
        Route::middleware('cloisonner.residence')->group(function (): void {
            Route::get('sejours/{sejour}', [SejoursController::class, 'afficher'])->whereNumber('sejour')->name('sejours.afficher');
            Route::post('sejours/{sejour}/confirmation', [SejoursController::class, 'confirmer'])->whereNumber('sejour')->name('sejours.confirmer');
            Route::post('sejours/{sejour}/code-arrivee/renvoi', [SejoursController::class, 'renvoyerLeCode'])->whereNumber('sejour')->middleware('throttle:6,1')->name('sejours.code.renvoyer');
            Route::post('sejours/{sejour}/code-arrivee/nouveau', [SejoursController::class, 'nouveauCode'])->whereNumber('sejour')->name('sejours.code.nouveau');
            // Réduction sur séjour : un administrateur propose, seul le trésorier désigné confirme (CdC § 6.1).
            Route::post('sejours/{sejour}/reduction', [SejoursController::class, 'proposerUneReduction'])->whereNumber('sejour')->name('sejours.reduction.proposer');

            // Check-in / check-out / prolongation (P2-SEJ-01, 03, 04 ; visibilité P2-BO-02) : même
            // service que l'espace agent (routes/api_v1/agent.php), simplement accessible ici aussi.
            Route::get('sejours/{sejour}/occupants', [SejoursController::class, 'occupants'])->whereNumber('sejour')->name('sejours.occupants');
            Route::post('sejours/{sejour}/occupants/{occupant}/piece', [SejoursController::class, 'deposerLaPieceDUnOccupant'])->whereNumber('sejour')->whereNumber('occupant')->name('sejours.occupants.piece');
            Route::post('sejours/{sejour}/check-in', [SejoursController::class, 'checkIn'])->whereNumber('sejour')->name('sejours.check-in');
            Route::get('sejours/{sejour}/consommations', [SejoursController::class, 'consommations'])->whereNumber('sejour')->name('sejours.consommations');
            Route::post('sejours/{sejour}/check-out', [SejoursController::class, 'checkOutSejour'])->whereNumber('sejour')->name('sejours.check-out');
            Route::put('sejours/{sejour}/depart', [SejoursController::class, 'prolonger'])->whereNumber('sejour')->name('sejours.depart.modifier');

            // États des lieux d'entrée et de sortie (P2-SEJ-02). Pas de scopeBindings() : le nom
            // français de la relation (`etatsDesLieux`) ne suit pas la pluralisation anglaise que
            // Laravel devine du paramètre — l'appartenance au séjour est vérifiée dans le contrôleur.
            Route::prefix('sejours/{sejour}/etats-des-lieux')->name('sejours.etats-des-lieux.')->group(function (): void {
                Route::get('/', [EtatsDesLieuxController::class, 'index'])->name('index');
                Route::post('/', [EtatsDesLieuxController::class, 'etablir'])->name('etablir');
                Route::get('pdf', [EtatsDesLieuxController::class, 'pdf'])->name('pdf');
                Route::post('{etatDesLieu}/lignes', [EtatsDesLieuxController::class, 'ajouterUneLigne'])->whereNumber('etatDesLieu')->name('lignes.ajouter');
                Route::post('{etatDesLieu}/lignes/{ligne}/photos', [EtatsDesLieuxController::class, 'ajouterUnePhoto'])->whereNumber('etatDesLieu')->whereNumber('ligne')->name('lignes.photos.ajouter');
                Route::post('{etatDesLieu}/signature', [EtatsDesLieuxController::class, 'signer'])->whereNumber('etatDesLieu')->name('signer');
            });
        });

        // Missions de ménage (P2-MEN-01, CdC § 6.4) : consultation, affectation à un agent de
        // terrain, ménage demandé ad hoc — visible à la gestion quotidienne, comme les séjours.
        // Le déroulement (démarrer / terminer) reste côté agent (routes/api_v1/agent.php).
        Route::prefix('missions')->name('missions.')->group(function (): void {
            Route::get('/', [MissionsController::class, 'index'])->name('index');
            Route::get('{mission}', [MissionsController::class, 'afficher'])->whereNumber('mission')->name('afficher');
            Route::put('{mission}/affectation', [MissionsController::class, 'affecter'])->whereNumber('mission')->name('affecter');
        });
        Route::post('logements/{logement}/missions', [MissionsController::class, 'demander'])
            ->middleware('cloisonner.residence')->name('logements.missions.demander');

        // Direction : devis établis, en attente de transformation (CdC § 9.1).
        Route::get('devis', [DevisController::class, 'index'])->name('devis.index');
        Route::get('devis/export', [DevisController::class, 'exporter'])->name('devis.exporter');

        // Propriétaires (ex-fournisseurs) et pièces de leur dossier.
        Route::prefix('proprietaires')->name('proprietaires.')->group(function (): void {
            Route::get('/', [ProprietairesController::class, 'index'])->name('index');
            // Avant {proprietaire} : « export » ne doit jamais être pris pour un identifiant.
            Route::get('export', [ProprietairesController::class, 'exporter'])->name('exporter');
            Route::post('/', [ProprietairesController::class, 'creer'])->name('creer');
            Route::get('{proprietaire}', [ProprietairesController::class, 'afficher'])->name('afficher');
            Route::put('{proprietaire}', [ProprietairesController::class, 'modifier'])->name('modifier');

            Route::prefix('{proprietaire}/pieces')->name('pieces.')->scopeBindings()->group(function (): void {
                Route::get('/', [PiecesProprietaireController::class, 'index'])->name('index');
                Route::post('/', [PiecesProprietaireController::class, 'deposer'])->name('deposer');
                Route::get('{piece}/telecharger', [PiecesProprietaireController::class, 'telecharger'])->name('telecharger');
                Route::put('{piece}/decision', [PiecesProprietaireController::class, 'decider'])->name('decider');
                Route::delete('{piece}', [PiecesProprietaireController::class, 'supprimer'])->name('supprimer');
            });
        });

        // Apporteurs d'affaires : liste, mandat de commission, commissions et solde dû.
        Route::prefix('apporteurs')->name('apporteurs.')->group(function (): void {
            Route::get('/', [ApporteursController::class, 'index'])->name('index');
            Route::post('/', [ApporteursController::class, 'creer'])->name('creer');
            Route::put('{apporteur}', [ApporteursController::class, 'modifier'])->name('modifier');
            Route::get('{apporteur}/commissions', [ApporteursController::class, 'commissions'])->name('commissions');
        });

        // Transferts en attente / traités (CdC § 6.6) : affectation chauffeur et véhicule, code
        // de prise en charge remis au client — jamais lu ici (CdC § 11).
        Route::prefix('transferts')->name('transferts.')->group(function (): void {
            Route::get('/', [TransfertsController::class, 'index'])->name('index');
            Route::post('{transfert}/affecter', [TransfertsController::class, 'affecter'])->whereNumber('transfert')->name('affecter');
            Route::post('{transfert}/annuler', [TransfertsController::class, 'annuler'])->whereNumber('transfert')->name('annuler');
        });

        // Chauffeurs et leurs véhicules (CdC § 6.6) : même patron que les apporteurs d'affaires.
        Route::prefix('chauffeurs')->name('chauffeurs.')->group(function (): void {
            Route::get('/', [ChauffeursController::class, 'index'])->name('index');
            Route::post('/', [ChauffeursController::class, 'creer'])->name('creer');
            Route::put('{chauffeur}', [ChauffeursController::class, 'modifier'])->name('modifier');

            Route::prefix('{chauffeur}/vehicules')->name('vehicules.')->group(function (): void {
                Route::get('/', [VehiculesController::class, 'index'])->name('index');
                Route::post('/', [VehiculesController::class, 'creer'])->name('creer');
                Route::put('{vehicule}', [VehiculesController::class, 'modifier'])->whereNumber('vehicule')->name('modifier');
            });
        });

        // Restaurateurs partenaires (CdC — « Repas et boissons ») : même patron que les
        // apporteurs d'affaires. Le pourcentage plateforme ne se change que par double
        // validation (proposer ici, décision par la file générique « changements »).
        Route::prefix('restaurateurs')->name('restaurateurs.')->group(function (): void {
            Route::get('/', [RestaurateursController::class, 'index'])->name('index');
            Route::post('/', [RestaurateursController::class, 'creer'])->name('creer');
            Route::put('{restaurateur}', [RestaurateursController::class, 'modifier'])->name('modifier');
            Route::post('{restaurateur}/pourcentage/proposer', [RestaurateursController::class, 'proposerLePourcentage'])
                ->middleware('profil:super_administrateur,administrateur')->name('pourcentage.proposer');
            Route::get('{restaurateur}/dette', [RestaurateursController::class, 'dette'])->name('dette');

            // La carte du restaurateur, gérée au besoin par le back office (en plus de son propre espace).
            Route::prefix('{restaurateur}/produits')->name('produits.')->scopeBindings()->group(function (): void {
                Route::get('/', [ProduitsRepasController::class, 'index'])->name('index');
                Route::post('/', [ProduitsRepasController::class, 'creer'])->name('creer');
                Route::put('{produit}', [ProduitsRepasController::class, 'modifier'])->name('modifier');
            });
        });

        // Livreurs de repas (CdC — « Repas et boissons », espace livreur) : même patron.
        Route::prefix('livreurs')->name('livreurs.')->group(function (): void {
            Route::get('/', [LivreursController::class, 'index'])->name('index');
            Route::post('/', [LivreursController::class, 'creer'])->name('creer');
            Route::put('{livreur}', [LivreursController::class, 'modifier'])->name('modifier');
            Route::get('{livreur}/gains', [LivreursController::class, 'gains'])->name('gains');
        });

        // Commandes de repas : suivi, confirmation (règlement vérifié), affectation d'un
        // livreur (code de livraison émis, jamais exposé ici), refus motivé.
        Route::prefix('commandes-repas')->name('commandes-repas.')->group(function (): void {
            Route::get('/', [CommandesRepasController::class, 'index'])->name('index');
            Route::post('{commande}/confirmer', [CommandesRepasController::class, 'confirmer'])->name('confirmer');
            Route::post('{commande}/affecter-livreur', [CommandesRepasController::class, 'affecterUnLivreur'])->name('affecter-livreur');
            Route::post('{commande}/refuser', [CommandesRepasController::class, 'refuser'])->name('refuser');
        });

        // Notifications : journal et relance manuelle (CdC § 13.2).
        Route::prefix('notifications')->name('notifications.')->group(function (): void {
            Route::get('/', [NotificationsController::class, 'index'])->name('index');
            Route::post('{notification}/relance', [NotificationsController::class, 'relancer'])->name('relancer');
        });

        // Clients ordinaires : liste, fiche, bascule de TVA (réception) et liste noire (CdC § 5).
        Route::prefix('clients')->name('clients.')->group(function (): void {
            Route::get('/', [ClientsController::class, 'index'])->name('index');
            // Avant {client} : « export » ne doit jamais être pris pour un identifiant de client.
            Route::get('export', [ClientsController::class, 'exporter'])->name('exporter');
            Route::get('{client}', [ClientsController::class, 'afficher'])->name('afficher');
            Route::put('{client}/fiche', [ClientsController::class, 'modifierLaFiche'])->name('fiche');
            Route::put('{client}/tva', [ClientsController::class, 'basculerLaTva'])->name('tva');
            // Mise en/hors liste noire : réservée à un administrateur, vérifié dans le contrôleur.
            Route::put('{client}/liste-noire', [ClientsController::class, 'basculerLaListeNoire'])->name('liste-noire');
        });

        // Clients à terme : instruction des demandes de ligne de crédit (CdC § 5.1, 5.3).
        Route::prefix('clients-a-terme')->name('clients-a-terme.')->group(function (): void {
            Route::get('/', [ComptesATermeController::class, 'index'])->name('index');
            // Avant {client} : « export » ne doit jamais être pris pour un identifiant de client.
            Route::get('export', [ComptesATermeController::class, 'exporter'])->name('exporter');
            Route::get('{client}', [ComptesATermeController::class, 'afficher'])->name('afficher');
            Route::put('{client}/decision', [ComptesATermeController::class, 'decider'])->name('decider');

            Route::prefix('{client}/pieces')->name('pieces.')->group(function (): void {
                Route::get('/', [PiecesClientController::class, 'index'])->name('index');
                Route::post('/', [PiecesClientController::class, 'deposer'])->name('deposer');
                Route::get('{piece}/telecharger', [PiecesClientController::class, 'telecharger'])->name('telecharger');
                Route::put('{piece}/decision', [PiecesClientController::class, 'decider'])->name('decider');
                Route::delete('{piece}', [PiecesClientController::class, 'supprimer'])->name('supprimer');
            });
        });

        // Catalogue : résidence (le site) → logements (les unités).
        // Index et création à part : cloisonner.residence n'a rien à cloisonner tant qu'aucune
        // résidence n'est encore résolue par la route (l'index filtre lui-même sa requête).
        Route::prefix('residences')->name('residences.')->group(function (): void {
            Route::get('/', [ResidencesController::class, 'index'])->name('index');
            Route::get('export', [ResidencesController::class, 'exporter'])->name('exporter');
            Route::post('/', [ResidencesController::class, 'creer'])->name('creer');
        });

        // Un gestionnaire n'atteint que SES résidences : 404, pas 403, au-delà (CdC § 9.5).
        Route::prefix('residences')->name('residences.')->middleware('cloisonner.residence')->group(function (): void {
            Route::get('{residence}', [ResidencesController::class, 'afficher'])->name('afficher');
            Route::put('{residence}', [ResidencesController::class, 'modifier'])->name('modifier');
            Route::delete('{residence}', [ResidencesController::class, 'supprimer'])->name('supprimer');
            // Bouton « Occupée / Disponible ».
            Route::put('{residence}/disponibilite', [CalendrierController::class, 'disponibilite'])->name('disponibilite');

            // scopeBindings : un logement n'est trouvé que DANS sa résidence —
            // /residences/1/logements/9 répond 404 si le logement 9 appartient à la résidence 2.
            Route::prefix('{residence}/logements')->name('logements.')->scopeBindings()->group(function (): void {
                Route::get('/', [LogementsController::class, 'index'])->name('index');
                Route::post('/', [LogementsController::class, 'creer'])->name('creer');
                Route::get('{logement}', [LogementsController::class, 'afficher'])->name('afficher');
                Route::put('{logement}', [LogementsController::class, 'modifier'])->name('modifier');
                Route::delete('{logement}', [LogementsController::class, 'supprimer'])->name('supprimer');

                // Prix : propriétaire (négocié) et de vente (double validation).
                Route::get('{logement}/prix', [PrixLogementController::class, 'afficher'])->name('prix.afficher');
                Route::post('{logement}/prix/proprietaire', [PrixLogementController::class, 'prixProprietaire'])->name('prix.proprietaire');
                Route::post('{logement}/prix/vente', [PrixLogementController::class, 'prixDeVente'])
                    ->middleware('profil:super_administrateur,administrateur')->name('prix.vente');
                Route::post('{logement}/pourcentage-entreprise', [PrixLogementController::class, 'pourcentageEntreprise'])
                    ->middleware('profil:super_administrateur,administrateur')->name('pourcentage-entreprise.proposer');

                // Blocage de dates : maintenance, usage du propriétaire, saison fermée.
                Route::get('{logement}/blocages', [CalendrierController::class, 'blocages'])->name('blocages.index');
                Route::post('{logement}/blocages', [CalendrierController::class, 'bloquer'])->name('blocages.creer');
                Route::delete('{logement}/blocages/{blocage}', [CalendrierController::class, 'debloquer'])->name('blocages.supprimer');

                Route::get('{logement}/publication', [PublicationLogementController::class, 'afficher'])->name('publication.afficher');
                Route::post('{logement}/publication', [PublicationLogementController::class, 'agir'])->name('publication.agir');

                Route::prefix('{logement}/photos')->name('photos.')->group(function (): void {
                    Route::get('/', [PhotosLogementController::class, 'index'])->name('index');
                    Route::post('/', [PhotosLogementController::class, 'ajouter'])->name('ajouter');
                    Route::put('ordre', [PhotosLogementController::class, 'reordonner'])->name('reordonner');
                    Route::put('{photo}', [PhotosLogementController::class, 'modifier'])->whereNumber('photo')->name('modifier');
                    Route::post('{photo}/recadrage', [PhotosLogementController::class, 'recadrer'])->whereNumber('photo')->name('recadrer');
                    Route::delete('{photo}', [PhotosLogementController::class, 'supprimer'])->whereNumber('photo')->name('supprimer');
                });
            });
        });
    });

    // Écrans réservés aux administrateurs (CdC § 9 et § 12).
    Route::middleware('profil:super_administrateur,administrateur')->group(function (): void {
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');

        // Modération des avis vérifiés de fin de séjour (CdC § 5.1, P2-AVI-01).
        Route::prefix('avis')->name('avis.')->group(function (): void {
            Route::get('/', [AvisController::class, 'index'])->name('index');
            // Avant {avis} : « export » ne doit jamais être pris pour un identifiant de avis.
            Route::get('export', [AvisController::class, 'exporter'])->name('exporter');
            Route::put('{avis}/decision', [AvisController::class, 'decider'])->name('decider');
        });

        // Caisse — circuit de preuve : validation, preuve, finalisation, rejet, décaissements.
        Route::prefix('caisse')->name('caisse.')->group(function (): void {
            Route::post('decaissements', [CaisseController::class, 'decaisser'])->name('decaissements.saisir');
            Route::put('reglements/{reglement}/validation', [CaisseController::class, 'valider'])->name('reglements.valider');
            Route::post('reglements/{reglement}/preuve', [CaisseController::class, 'joindreLaPreuve'])->name('reglements.preuve.joindre');
            Route::get('reglements/{reglement}/preuve', [CaisseController::class, 'preuve'])->name('reglements.preuve.lire');
            Route::put('reglements/{reglement}/finalisation', [CaisseController::class, 'finaliser'])->name('reglements.finaliser');
            Route::put('reglements/{reglement}/rejet', [CaisseController::class, 'rejeter'])->name('reglements.rejeter');
        });

        // Tarification : grille nuitée, vérification automatique, simulation sur une réservation réelle.
        Route::prefix('tarification')->name('tarification.')->group(function (): void {
            Route::get('grille', [GrilleTarifaireController::class, 'afficher'])->name('grille.afficher');
            Route::put('grille', [GrilleTarifaireController::class, 'enregistrer'])->name('grille.enregistrer');
            Route::get('verification', [GrilleTarifaireController::class, 'verifier'])->name('verification');
            Route::get('simulation', [GrilleTarifaireController::class, 'simuler'])->name('simulation');
            // Pourcentage entreprise (CdC § 7.3) : taux global à double validation, bandeau des dérogations.
            Route::post('pourcentage-entreprise', [GrilleTarifaireController::class, 'proposerLePourcentageEntreprise'])->name('pourcentage-entreprise.proposer');
            Route::get('derogations', [GrilleTarifaireController::class, 'derogations'])->name('derogations');
        });

        // Barème des transferts (CdC § 6.6) : zone (commune) × type de véhicule → prix, réservé
        // administrateur, comme la grille tarifaire.
        Route::prefix('baremes-transfert')->name('baremes-transfert.')->group(function (): void {
            Route::get('/', [BaremesTransfertController::class, 'index'])->name('index');
            Route::post('/', [BaremesTransfertController::class, 'creer'])->name('creer');
            Route::put('{bareme}', [BaremesTransfertController::class, 'modifier'])->name('modifier');
            Route::delete('{bareme}', [BaremesTransfertController::class, 'supprimer'])->name('supprimer');
        });

        // Barème de livraison repas (CdC — « Barème de livraison repas ») : forfait par
        // résidence, réservé administrateur, comme le barème des transferts.
        Route::prefix('baremes-livraison-repas')->name('baremes-livraison-repas.')->group(function (): void {
            Route::get('/', [BaremesLivraisonRepasController::class, 'index'])->name('index');
            Route::post('/', [BaremesLivraisonRepasController::class, 'creer'])->name('creer');
            Route::put('{bareme}', [BaremesLivraisonRepasController::class, 'modifier'])->name('modifier');
        });

        // Factures normalisées électroniques (FNE, DGI — CdC § 9.4) : tout l'écran est réservé
        // à un administrateur (même restriction que le menu, `ECRANS_BACKOFFICE.factures`) ; la
        // transmission et l'avoir revérifient quand même dans le contrôleur, comme la caisse.
        Route::prefix('factures')->name('factures.')->group(function (): void {
            Route::get('/', [FacturesController::class, 'index'])->name('index');
            // Avant {facture} : « export » ne doit jamais être pris pour un identifiant de facture.
            Route::get('export', [FacturesController::class, 'exporter'])->name('exporter');
            Route::post('/', [FacturesController::class, 'creer'])->name('creer');
            Route::get('{facture}', [FacturesController::class, 'afficher'])->name('afficher');
            Route::get('{facture}/pdf', [FacturesController::class, 'pdf'])->name('pdf');
            Route::put('{facture}/transmission', [FacturesController::class, 'transmettre'])->name('transmettre');
            Route::post('{facture}/avoir', [FacturesController::class, 'avoir'])->name('avoir');
        });

        // Prix négociés par client et par type de logement : ils priment sur toute la grille (CdC § 7.3).
        Route::prefix('prix-negocies')->name('prix-negocies.')->group(function (): void {
            Route::get('/', [PrixNegocieController::class, 'index'])->name('index');
            // Avant {prixNegocie} : « export » ne doit jamais être pris pour un identifiant de prixNegocie.
            Route::get('export', [PrixNegocieController::class, 'exporter'])->name('exporter');
            Route::post('/', [PrixNegocieController::class, 'enregistrer'])->name('enregistrer');
            Route::put('{prixNegocie}/desactivation', [PrixNegocieController::class, 'desactiver'])->name('desactiver');
        });

        // Codes promo : réduction en pourcentage ou en montant, période de validité, activable par résidence (CdC § 7.3).
        Route::prefix('codes-promo')->name('codes-promo.')->group(function (): void {
            Route::get('/', [CodePromoController::class, 'index'])->name('index');
            // Avant {codePromo} : « export » ne doit jamais être pris pour un identifiant de codePromo.
            Route::get('export', [CodePromoController::class, 'exporter'])->name('exporter');
            Route::post('/', [CodePromoController::class, 'creer'])->name('creer');
            Route::put('{codePromo}', [CodePromoController::class, 'modifier'])->name('modifier');
        });

        // Demandes d'annulation (P2-SEJ-06, CdC § 6.1) : instruction réservée aux administrateurs,
        // comme tout décaissement (le remboursement éventuel en est un).
        Route::prefix('demandes-annulation')->name('demandes-annulation.')->group(function (): void {
            Route::get('/', [DemandesAnnulationController::class, 'index'])->name('index');
            Route::post('{demande}/acceptation', [DemandesAnnulationController::class, 'accepter'])->name('accepter');
            Route::post('{demande}/rejet', [DemandesAnnulationController::class, 'rejeter'])->name('rejeter');
        });

        // Tickets d'assistance (P2-BO-03, CdC § 6.1) : file en LECTURE seule ici — l'instruction
        // (répondre, fermer) revient à l'espace assistance (routes/api_v1/assistance.php).
        Route::get('tickets-assistance', [TicketsAssistanceController::class, 'index'])->name('tickets-assistance.index');

        // Réclamations (P2-BO-03, CdC § 6.1) : instruction réservée aux administrateurs — un
        // avoir / geste commercial se PROPOSE ici, mais se confirme dans la file générique des
        // changements à valider (changements/{changement}/decision), par LE trésorier désigné.
        Route::prefix('reclamations')->name('reclamations.')->group(function (): void {
            Route::get('/', [ReclamationsController::class, 'index'])->name('index');
            Route::post('{reclamation}/fermeture', [ReclamationsController::class, 'fermer'])->whereNumber('reclamation')->name('fermer');
            Route::post('{reclamation}/avoir', [ReclamationsController::class, 'proposerUnAvoir'])->whereNumber('reclamation')->name('avoir.proposer');
        });

        // Double validation : file des changements en attente d'un second administrateur.
        Route::get('changements', [ChangementsController::class, 'index'])->name('changements.index');
        Route::put('changements/{changement}/decision', [ChangementsController::class, 'decider'])->name('changements.decider');

        Route::get('parametres', [ParametresController::class, 'index'])->name('parametres.index');
        Route::put('parametres/{onglet}', [ParametresController::class, 'enregistrer'])->name('parametres.enregistrer');

        // Comptes du personnel : création, identifiant généré, rattachement à une agence et à des résidences (CdC § 9.5).
        Route::prefix('personnel')->name('personnel.')->group(function (): void {
            Route::get('/', [PersonnelController::class, 'index'])->name('index');
            Route::get('export', [PersonnelController::class, 'exporter'])->name('exporter');
            Route::post('/', [PersonnelController::class, 'creer'])->name('creer');
            Route::put('{utilisateur}/residences', [PersonnelController::class, 'rattacherAuxResidences'])->name('residences');
        });

        // Agences : le guichet d'encaissement auquel un administrateur ou un gestionnaire est rattaché (CdC § 8.1, 9.5).
        Route::prefix('agences')->name('agences.')->group(function (): void {
            Route::get('/', [AgencesController::class, 'index'])->name('index');
            // Avant {agence} : « export » ne doit jamais être pris pour un identifiant de agence.
            Route::get('export', [AgencesController::class, 'exporter'])->name('exporter');
            Route::post('/', [AgencesController::class, 'creer'])->name('creer');
            Route::put('{agence}', [AgencesController::class, 'modifier'])->name('modifier');
        });

        // Paramètres › Divers (CdC § 12, P1-BO-10) : blog, bannières, carrousel, lettre d'information.
        // « Modèles de messages » n'a pas de routes propres, voir l'onglet `parametres/messages`.
        Route::prefix('articles')->name('articles.')->group(function (): void {
            Route::get('/', [ArticlesController::class, 'index'])->name('index');
            // Avant {article} : « export » ne doit jamais être pris pour un identifiant de article.
            Route::get('export', [ArticlesController::class, 'exporter'])->name('exporter');
            Route::get('{article}', [ArticlesController::class, 'afficher'])->name('afficher');
            Route::post('/', [ArticlesController::class, 'creer'])->name('creer');
            Route::put('{article}', [ArticlesController::class, 'modifier'])->name('modifier');
        });

        Route::prefix('bannieres')->name('bannieres.')->group(function (): void {
            Route::get('/', [BannieresController::class, 'index'])->name('index');
            // Avant {banniere} : « export » ne doit jamais être pris pour un identifiant de banniere.
            Route::get('export', [BannieresController::class, 'exporter'])->name('exporter');
            Route::post('/', [BannieresController::class, 'creer'])->name('creer');
            Route::put('{banniere}', [BannieresController::class, 'modifier'])->name('modifier');
            Route::delete('{banniere}', [BannieresController::class, 'supprimer'])->name('supprimer');
        });

        Route::prefix('carrousel')->name('carrousel.')->group(function (): void {
            Route::get('/', [CarrouselController::class, 'index'])->name('index');
            // Avant {diapositive} : « export » ne doit jamais être pris pour un identifiant de diapositive.
            Route::get('export', [CarrouselController::class, 'exporter'])->name('exporter');
            Route::post('/', [CarrouselController::class, 'creer'])->name('creer');
            Route::put('{diapositive}', [CarrouselController::class, 'modifier'])->name('modifier');
            Route::delete('{diapositive}', [CarrouselController::class, 'supprimer'])->name('supprimer');
        });

        Route::prefix('newsletter')->name('newsletter.')->group(function (): void {
            Route::get('/', [NewsletterController::class, 'index'])->name('index');
            // Avant {abonne} : « export » ne doit jamais être pris pour un identifiant de abonne.
            Route::get('export', [NewsletterController::class, 'exporter'])->name('exporter');
            Route::put('{abonne}/desabonner', [NewsletterController::class, 'desactiver'])->name('desabonner');
        });
    });
});
