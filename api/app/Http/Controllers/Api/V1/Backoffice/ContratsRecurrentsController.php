<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Models\User;
use App\Domain\Maintenance\Enums\PeriodiciteContrat;
use App\Domain\Maintenance\Models\ContratRecurrent;
use App\Domain\Maintenance\Services\ContratsRecurrents;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ContratRecurrentResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Contrats récurrents d'un logement (P2-MNT-02) : nom, périodicité, prochain rappel. */
final class ContratsRecurrentsController extends Controller
{
    public function __construct(private readonly ContratsRecurrents $contrats) {}

    public function index(Residence $residence, Logement $logement): JsonResponse
    {
        $contrats = ContratRecurrent::query()->where('logement_id', $logement->id)->orderBy('prochain_rappel')->get();

        return ReponseApi::succes(ContratRecurrentResource::collection($contrats));
    }

    public function creer(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $saisie = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'periodicite' => ['required', Rule::enum(PeriodiciteContrat::class)],
            'prochain_rappel' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['nom' => 'nom', 'periodicite' => 'périodicité', 'prochain_rappel' => 'prochain rappel', 'notes' => 'notes']);

        /** @var User $auteur */
        $auteur = $request->user();
        $contrat = $this->contrats->creer(
            $logement, $saisie['nom'], PeriodiciteContrat::from($saisie['periodicite']),
            Carbon::parse($saisie['prochain_rappel']), $saisie['notes'] ?? null, $auteur,
        );

        return ReponseApi::cree(new ContratRecurrentResource($contrat), 'Contrat récurrent créé.');
    }

    public function modifier(Request $request, Residence $residence, Logement $logement, ContratRecurrent $contrat): JsonResponse
    {
        $this->exigerAppartenance($logement, $contrat);
        $saisie = $request->validate([
            'nom' => ['sometimes', 'string', 'max:150'],
            'periodicite' => ['sometimes', Rule::enum(PeriodiciteContrat::class)],
            'prochain_rappel' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ], [], ['nom' => 'nom', 'periodicite' => 'périodicité', 'prochain_rappel' => 'prochain rappel', 'notes' => 'notes']);

        if (isset($saisie['periodicite'])) {
            $saisie['periodicite'] = PeriodiciteContrat::from($saisie['periodicite']);
        }
        if (isset($saisie['prochain_rappel'])) {
            $saisie['prochain_rappel'] = Carbon::parse($saisie['prochain_rappel']);
        }

        $contrat = $this->contrats->modifier($contrat, $saisie);

        return ReponseApi::succes(new ContratRecurrentResource($contrat), 'Contrat modifié.');
    }

    public function desactiver(Residence $residence, Logement $logement, ContratRecurrent $contrat): JsonResponse
    {
        $this->exigerAppartenance($logement, $contrat);
        $this->contrats->desactiver($contrat);

        return ReponseApi::succes(null, 'Contrat désactivé.');
    }

    private function exigerAppartenance(Logement $logement, ContratRecurrent $contrat): void
    {
        if ($contrat->logement_id !== $logement->id) {
            abort(404);
        }
    }
}
