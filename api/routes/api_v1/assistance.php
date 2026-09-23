<?php

use App\Http\Controllers\Api\V1\Assistance\TicketsController;
use Illuminate\Support\Facades\Route;

// Espace assistance (self-service, CdC § 6.1) : Profil::AgentAssistance — jusqu'ici sans
// aucune route ni écran (audit du 22/09/2026, P2-AST-01). Traite les tickets soulevés par
// les clients PENDANT leur séjour : liste, réponse, fermeture. Les réclamations (APRÈS un
// séjour, avec avoir éventuel à double validation) restent instruites par le back office
// (routes/api_v1/backoffice.php) — un geste commercial est une décision d'administrateur.

Route::prefix('assistance')->name('assistance.')->middleware(['connecte', 'profil:agent_assistance'])->group(function (): void {
    Route::get('tickets', [TicketsController::class, 'index'])->name('tickets.index');
    Route::post('tickets/{ticket}/reponse', [TicketsController::class, 'repondre'])->whereNumber('ticket')->name('tickets.repondre');
    Route::post('tickets/{ticket}/fermeture', [TicketsController::class, 'fermer'])->whereNumber('ticket')->name('tickets.fermer');
});
