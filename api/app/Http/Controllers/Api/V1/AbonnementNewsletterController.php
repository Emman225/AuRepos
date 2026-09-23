<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contenu\Models\AbonneNewsletter;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Inscription publique à la lettre d'information (CdC § 12), en libre-service. */
final class AbonnementNewsletterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'nom' => ['nullable', 'string', 'max:150'],
        ]);

        // Idempotent : une adresse déjà désinscrite qui s'inscrit à nouveau redevient active.
        AbonneNewsletter::query()->updateOrCreate(
            ['email' => $saisie['email']],
            ['nom' => $saisie['nom'] ?? null, 'actif' => true, 'abonne_le' => now(), 'desabonne_le' => null],
        );

        return ReponseApi::succes(null, 'Inscription à la lettre d’information enregistrée.', 201);
    }
}
