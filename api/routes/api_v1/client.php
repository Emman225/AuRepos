<?php

use App\Http\Controllers\Api\V1\Client\AvisController;
use App\Http\Controllers\Api\V1\Client\CommandesExtrasController;
use App\Http\Controllers\Api\V1\Client\CommandesRepasController;
use App\Http\Controllers\Api\V1\Client\CompteController;
use App\Http\Controllers\Api\V1\Client\DevisController;
use App\Http\Controllers\Api\V1\Client\ExtrasController;
use App\Http\Controllers\Api\V1\Client\ReclamationsController;
use App\Http\Controllers\Api\V1\Client\RestaurateursController;
use App\Http\Controllers\Api\V1\Client\SejoursController;
use App\Http\Controllers\Api\V1\Client\TicketsAssistanceController;
use App\Http\Controllers\Api\V1\Client\TransfertsController;
use App\Http\Controllers\Api\V1\PaiementsController;
use Illuminate\Support\Facades\Route;

// Espace client (site et application mobile). Fermé quand le site est en construction.

Route::prefix('client')->name('client.')->middleware(['connecte', 'profil:client', 'site.ouvert'])->group(function (): void {
    // Détail du compte : coordonnées et régime de facturation (CdC § 5.3).
    Route::get('compte', [CompteController::class, 'monCompte'])->name('compte.afficher');

    // Devenir client à terme : ligne de crédit pour les organisations (CdC § 5.1, 5.3).
    Route::get('compte/a-terme', [CompteController::class, 'aTerme'])->name('compte.a-terme');
    Route::post('compte/a-terme/demande', [CompteController::class, 'demanderATerme'])->name('compte.a-terme.demander');
    Route::post('compte/a-terme/pieces', [CompteController::class, 'deposerUnePiece'])->name('compte.a-terme.pieces.deposer');

    Route::get('sejours', [SejoursController::class, 'index'])->name('sejours.index');
    // 10 réservations par minute : largement assez pour un humain, trop peu pour bloquer un calendrier par robot.
    Route::post('sejours', [SejoursController::class, 'reserver'])->middleware('throttle:10,1')->name('sejours.reserver');
    Route::get('sejours/{reference}', [SejoursController::class, 'afficher'])->name('sejours.afficher');
    Route::post('sejours/{reference}/annulation', [SejoursController::class, 'annuler'])->name('sejours.annuler');
    // Séjour confirmé (ou déjà arrivé) : une DEMANDE d'annulation, instruite par la réception (P2-SEJ-06).
    Route::post('sejours/{reference}/demande-annulation', [SejoursController::class, 'demanderAnnulation'])->middleware('throttle:10,1')->name('sejours.demande-annulation');
    // Avis vérifié de fin de séjour (CdC § 5.1, P2-AVI-01) : un séjour clôturé, un seul avis.
    Route::post('sejours/{reference}/avis', [AvisController::class, 'soumettre'])->middleware('throttle:10,1')->name('sejours.avis.soumettre');

    // Assistance (P2-AST-01, CdC § 6.1) : un ticket PENDANT un séjour en cours (« arrivé »).
    Route::get('sejours/{reference}/tickets-assistance', [TicketsAssistanceController::class, 'index'])->name('sejours.tickets-assistance.index');
    Route::post('sejours/{reference}/tickets-assistance', [TicketsAssistanceController::class, 'soumettre'])->middleware('throttle:10,1')->name('sejours.tickets-assistance.soumettre');

    // Réclamations (P2-AST-01, CdC § 6.1) : APRÈS un séjour terminé, motif d'au moins 15 caractères.
    Route::get('sejours/{reference}/reclamations', [ReclamationsController::class, 'index'])->name('sejours.reclamations.index');
    Route::post('sejours/{reference}/reclamations', [ReclamationsController::class, 'soumettre'])->middleware('throttle:10,1')->name('sejours.reclamations.soumettre');

    // Mes extras et transferts (CdC § 5.2, § 6.6) : demande PENDANT un séjour déjà existant,
    // suivi et code de prise en charge (jamais la réservation d'un transfert, hors de portée ici).
    Route::get('sejours/{reference}/transferts', [TransfertsController::class, 'index'])->name('sejours.transferts.index');
    Route::post('sejours/{reference}/transferts', [TransfertsController::class, 'demander'])->middleware('throttle:20,1')->name('sejours.transferts.demander');

    // Extras (P2-EXT-01) : catalogue, puis commande PENDANT un séjour déjà arrivé (même garde
    // que les tickets d'assistance ci-dessus). Facturé par son propre guichet d'encaissement
    // (P2-TRF-03), jamais une ligne du net à payer figé du séjour.
    Route::get('extras', [ExtrasController::class, 'index'])->name('extras.index');
    Route::get('sejours/{reference}/extras', [CommandesExtrasController::class, 'index'])->name('sejours.extras.index');
    Route::post('sejours/{reference}/extras', [CommandesExtrasController::class, 'commander'])->middleware('throttle:20,1')->name('sejours.extras.commander');

    // Repas et boissons (CdC — « Repas et boissons ») : QUOI commander (restaurateurs actifs
    // et leur carte disponible), puis mes commandes PENDANT le séjour, avec le code de
    // livraison en clair une fois la commande en livraison (jamais avant, jamais après).
    Route::get('restaurateurs', [RestaurateursController::class, 'index'])->name('restaurateurs.index');
    Route::get('sejours/{reference}/commandes', [CommandesRepasController::class, 'index'])->name('sejours.commandes.index');
    Route::post('sejours/{reference}/commandes', [CommandesRepasController::class, 'commander'])->middleware('throttle:20,1')->name('sejours.commandes.commander');
    Route::get('sejours/{reference}/commandes/{commande}', [CommandesRepasController::class, 'afficher'])->whereNumber('commande')->name('sejours.commandes.afficher');

    // Réclamations REPAS (P4-API-08) : APRÈS une commande LIVRÉE, même règle de motif.
    Route::get('sejours/{reference}/commandes/{commande}/reclamations', [ReclamationsController::class, 'indexCommande'])->whereNumber('commande')->name('sejours.commandes.reclamations.index');
    Route::post('sejours/{reference}/commandes/{commande}/reclamations', [ReclamationsController::class, 'soumettreCommande'])->whereNumber('commande')->middleware('throttle:10,1')->name('sejours.commandes.reclamations.soumettre');

    // Mes devis : prix figés, transformation en réservation d'un clic, suppression = archivage (CdC § 5.1).
    Route::get('devis', [DevisController::class, 'index'])->name('devis.index');
    Route::post('devis', [DevisController::class, 'creer'])->middleware('throttle:20,1')->name('devis.creer');
    Route::get('devis/{reference}', [DevisController::class, 'afficher'])->name('devis.afficher');
    Route::post('devis/{reference}/transformation', [DevisController::class, 'transformer'])->middleware('throttle:10,1')->name('devis.transformer');
    Route::delete('devis/{reference}', [DevisController::class, 'archiver'])->name('devis.archiver');

    // Mes paiements : mes reçus et mon avance disponible.
    Route::get('paiements', [SejoursController::class, 'paiements'])->name('paiements');
    Route::get('recus/{numero}', [SejoursController::class, 'recu'])->name('recus');

    // Paiement en ligne : le client déclenche, le serveur conclut.
    Route::post('sejours/{reference}/paiement', [PaiementsController::class, 'initier'])->middleware('throttle:10,1')->name('paiement.initier');
    Route::get('paiements/{reference}', [PaiementsController::class, 'etat'])->middleware('throttle:30,1')->name('paiement.etat');
});
