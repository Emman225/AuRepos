<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Domain\Sejours\Services\BonsDeMiseADisposition;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace propriétaire › SES bons de mise à disposition (CdC § 7.2, P3-PRO-01). */
final class BonsController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function __construct(private readonly BonsDeMiseADisposition $bons) {}

    public function index(Request $request): JsonResponse
    {
        $proprietaire = $this->monProprietaire($request);

        $bons = BonDeMiseADisposition::query()->where('proprietaire_id', $proprietaire->id)
            ->with('sejour.logement.residence')->orderByDesc('id')->get();

        return ReponseApi::succes($bons->map(fn (BonDeMiseADisposition $b): array => $this->presenter($b)));
    }

    public function valider(Request $request, BonDeMiseADisposition $bon): JsonResponse
    {
        $proprietaire = $this->monProprietaire($request);
        if ($bon->proprietaire_id !== $proprietaire->id) {
            abort(404);
        }

        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $this->bons->valider($bon, $utilisateur);

        return ReponseApi::succes($this->presenter($bon->refresh()->load('sejour.logement.residence')), 'Bon validé.');
    }

    /** @return array<string, mixed> */
    private function presenter(BonDeMiseADisposition $bon): array
    {
        return [
            'id' => $bon->id, 'numero' => $bon->numero, 'etat' => $bon->etat,
            'nuitees' => $bon->nuitees, 'valide_automatiquement' => $bon->valide_automatiquement,
            'valide_le' => $bon->valide_le?->format('d/m/Y H:i'),
            'logement' => $bon->sejour->logement->nom, 'residence' => $bon->sejour->logement->residence->nom,
            'arrivee' => $bon->sejour->arrivee->format('d/m/Y'), 'depart' => $bon->sejour->depart->format('d/m/Y'),
        ];
    }
}
