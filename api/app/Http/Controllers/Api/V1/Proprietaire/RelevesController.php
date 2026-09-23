<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Partenaires\Models\RelevePropretaire;
use App\Domain\Partenaires\Services\RelevesProprietaires;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Espace propriétaire › SES relevés mensuels (CdC § 7.2, P3-PRO-03). */
final class RelevesController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function __construct(private readonly RelevesProprietaires $releves) {}

    public function index(Request $request): JsonResponse
    {
        $proprietaire = $this->monProprietaire($request);

        $releves = RelevePropretaire::query()->where('proprietaire_id', $proprietaire->id)->orderByDesc('periode')->get();

        return ReponseApi::succes($releves->map(fn (RelevePropretaire $r): array => [
            'id' => $r->id, 'periode' => $r->periode->format('m/Y'), 'nuitees_consommees' => $r->nuitees_consommees,
            'montant_brut' => $r->montant_brut, 'charges_refacturees' => $r->charges_refacturees, 'part_cautions' => $r->part_cautions,
            'tva' => $r->tva, 'retenue_taux' => $r->retenue_taux, 'retenue_montant' => $r->retenue_montant,
            'montant_net' => $r->montant_net, 'genere_le' => $r->genere_le->format('d/m/Y'),
        ]));
    }

    public function telecharger(Request $request, RelevePropretaire $releve): Response
    {
        $proprietaire = $this->monProprietaire($request);
        if ($releve->proprietaire_id !== $proprietaire->id) {
            abort(404);
        }

        return response($this->releves->pdf($releve), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="releve-'.$releve->periode->format('m-Y').'.pdf"',
        ]);
    }

    public function telechargerAttestation(Request $request, RelevePropretaire $releve): Response
    {
        $proprietaire = $this->monProprietaire($request);
        if ($releve->proprietaire_id !== $proprietaire->id) {
            abort(404);
        }

        return response($this->releves->attestationPdf($releve), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="attestation-retenue-'.$releve->periode->format('m-Y').'.pdf"',
        ]);
    }
}
