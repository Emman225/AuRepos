<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Domain\Tarification\Calcul\DemandeDeCalcul;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Chiffrage d'un séjour par la réception (réservation manuelle, devis, contrôle d'une grille).
 * Même moteur que le site public, avec en plus ce que seul le back office peut demander :
 * remise, extras, transfert, bascules de TVA du client.
 */
final class CalculSejourController extends Controller
{
    public function __invoke(Request $request, CalculDuSejour $calcul): JsonResponse
    {
        $saisie = $request->validate([
            'logement_id' => ['required', 'integer', Rule::exists('logements', 'id')],
            'arrivee' => ['required', 'date'],
            'depart' => ['required', 'date', 'after:arrivee'],
            'adultes' => ['required', 'integer', 'min:1', 'max:60'],
            'enfants' => ['nullable', 'integer', 'min:0', 'max:60'],
            'arrivee_tardive' => ['nullable', 'boolean'],
            'depart_tardif' => ['nullable', 'boolean'],
            'remise_pourcentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'extras' => ['nullable', 'array', 'max:50'],
            'extras.*.libelle' => ['required', 'string', 'max:150'],
            'extras.*.montant_ht' => ['required', 'integer', 'min:0', 'max:100000000'],
            'transfert_ht' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'tva_hebergement' => ['nullable', 'boolean'],
            'tva_transfert' => ['nullable', 'boolean'],
        ], [], [
            'logement_id' => 'logement', 'arrivee' => 'date d’arrivée', 'depart' => 'date de départ', 'adultes' => 'nombre d’adultes',
            'remise_pourcentage' => 'remise', 'transfert_ht' => 'montant du transfert',
        ]);

        $devis = $calcul->calculer(new DemandeDeCalcul(
            logement: Logement::findOrFail($saisie['logement_id']),
            arrivee: Carbon::parse($saisie['arrivee']),
            depart: Carbon::parse($saisie['depart']),
            adultes: (int) $saisie['adultes'],
            enfants: (int) ($saisie['enfants'] ?? 0),
            arriveeTardive: (bool) ($saisie['arrivee_tardive'] ?? false),
            departTardif: (bool) ($saisie['depart_tardif'] ?? false),
            remisePourcentage: (float) ($saisie['remise_pourcentage'] ?? 0),
            extras: array_values($saisie['extras'] ?? []),
            transfertHt: (int) ($saisie['transfert_ht'] ?? 0),
            tvaHebergementApplicable: (bool) ($saisie['tva_hebergement'] ?? true),
            tvaTransfertApplicable: (bool) ($saisie['tva_transfert'] ?? true),
        ));

        return ReponseApi::succes($devis->toArray());
    }
}
