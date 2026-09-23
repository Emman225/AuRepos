<?php

use App\Domain\Parametres\Services\Parametres;
use App\Http\Controllers\Api\V1\AbonnementNewsletterController;
use App\Http\Controllers\Api\V1\AccueilController;
use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\CatalogueController;
use App\Http\Controllers\Api\V1\EtatController;
use App\Http\Controllers\Api\V1\PaiementsController;
use App\Http\Controllers\Api\V1\ReferentielsController;
use App\Http\Controllers\Api\V1\SitemapController;
use App\Http\Middleware\IdentifierSiPossible;
use App\Support\Api\ReponseApi;
use Illuminate\Support\Facades\Route;

// Routes ouvertes à tous, sans connexion.

Route::get('etat', EtatController::class)->name('etat');

// Réglages dont le site et les applications ont besoin avant toute connexion :
// devise, horaires, plafond du paiement en ligne, mode « site en construction ».
// Jamais protégée par `site.ouvert` : c'est elle qui annonce que le site est fermé.
Route::get('configuration', fn (Parametres $parametres) => ReponseApi::succes($parametres->publics()))
    ->name('configuration');

// Listes de choix du site et des applications (éléments actifs seulement), en cascade :
//   /referentiels/communes?ville_id=1  ›  /referentiels/quartiers?commune_id=4
Route::get('referentiels/{slug}', [ReferentielsController::class, 'choix'])->name('referentiels.choix');

// Page d'accueil : résidences et logements mis en avant, promotions, témoignages (CdC § 5.1).
Route::get('accueil', AccueilController::class)->middleware('site.ouvert')->name('accueil');

// Fiche publique d'un logement : seuls les logements PUBLIÉS existent pour le public.
Route::get('catalogue/logements/{reference}', [CatalogueController::class, 'logement'])
    ->middleware('site.ouvert')->name('catalogue.logement');

Route::get('catalogue/logements/{reference}/disponibilite', [CatalogueController::class, 'disponibilite'])
    ->middleware(['site.ouvert', 'throttle:120,1'])->name('catalogue.disponibilite');

Route::post('catalogue/logements/{reference}/estimation', [CatalogueController::class, 'estimer'])
    ->middleware(['site.ouvert', 'throttle:60,1', IdentifierSiPossible::class])->name('catalogue.estimation');

Route::get('catalogue/recherche', [CatalogueController::class, 'rechercher'])
    ->middleware(['site.ouvert', 'throttle:120,1'])->name('catalogue.recherche');

// Rappel de la passerelle de paiement : elle n'a pas de session, son authenticité tient à sa signature.
// Jamais protégée par `site.ouvert` : un paiement en cours doit aboutir même site fermé.
Route::post('paiements/rappel', [PaiementsController::class, 'rappel'])->middleware('throttle:120,1')->name('paiements.rappel');

// Blog (CdC § 12) : seuls les articles publiés sont exposés.
Route::get('blog', [BlogController::class, 'index'])->middleware('site.ouvert')->name('blog.index');
Route::get('blog/{slug}', [BlogController::class, 'afficher'])->middleware('site.ouvert')->name('blog.afficher');

// Lettre d'information (CdC § 12) : inscription en libre-service.
Route::post('newsletter/abonnement', AbonnementNewsletterController::class)
    ->middleware(['site.ouvert', 'throttle:20,1'])->name('newsletter.abonnement');

// Sitemap XML (CdC § 12, référencement) : pages statiques + un lien par logement publié.
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
