<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Avances;
use App\Domain\Caisse\Services\Recus;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Fidelite\Services\PointsDeFidelite;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\CycleDuSejour;
use App\Domain\Sejours\Services\DemandesDAnnulation;
use App\Domain\Sejours\Services\ReservationDeSejour;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sejours\ReservationRequest;
use App\Http\Resources\Client\SejourResource;
use App\Http\Resources\Sejours\DemandeAnnulationResource;
use App\Support\Api\ErreurMetier;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Espace client › Mes séjours (CdC § 5.2 et § 5.3). Un client ne voit et ne touche que SES séjours. */
final class SejoursController extends Controller
{
    private const RELATIONS = ['logement.type', 'logement.residence.quartier.commune', 'occupants'];

    public function index(Request $request): JsonResponse
    {
        $page = Sejour::query()->with(self::RELATIONS)
            ->where('client_id', $request->user()?->getAuthIdentifier())
            ->orderByDesc('arrivee')->orderByDesc('id')
            ->paginate(min(50, max(5, (int) $request->query('par_page', 20))));

        return ReponseApi::page($page, SejourResource::class);
    }

    public function afficher(Request $request, string $reference): JsonResponse
    {
        return ReponseApi::succes(new SejourResource($this->leMien($request, $reference)));
    }

    public function reserver(ReservationRequest $request, ReservationDeSejour $reservation): JsonResponse
    {
        /** @var User $client */
        $client = $request->user();
        /** @var array{arrivee: string, depart: string, adultes: int, mode_reglement: string, reference_logement: string} $saisie */
        $saisie = $request->validated();

        $logement = Logement::query()->where('reference', $saisie['reference_logement'])->first()
            ?? throw new ErreurMetier('Ce logement n’est pas ouvert à la réservation.', 'logement_non_reservable', 422);

        $sejour = $reservation->reserver($client, $logement, $saisie);

        return ReponseApi::cree(
            new SejourResource($sejour->load(self::RELATIONS)),
            $sejour->expire_le
                ? 'Réservation enregistrée. Réglez-la avant le '.$sejour->expire_le->format('d/m/Y à H:i').' : passé ce délai, les dates seront libérées.'
                : 'Réservation enregistrée.',
        );
    }

    /**
     * Le client annule lui-même une simple DEMANDE (rien n'a été encaissé). Un séjour confirmé
     * passe par une demande d'annulation, instruite par la réception selon la politique (P2-SEJ-06).
     */
    public function annuler(Request $request, string $reference, CycleDuSejour $cycle): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);

        if ($sejour->etat !== EtatDuSejour::Demande) {
            throw new ErreurMetier(
                'Ce séjour est déjà '.mb_strtolower($sejour->etat->libelle()).' : adressez une demande d’annulation à la réception.',
                'annulation_a_instruire',
            );
        }

        /** @var User $client */
        $client = $request->user();
        $cycle->annuler($sejour, $client, 'Annulation par le client');

        return ReponseApi::succes(new SejourResource($sejour->refresh()->load(self::RELATIONS)), 'Réservation annulée : les dates sont libérées.');
    }

    /**
     * Un séjour confirmé (ou déjà arrivé) ne s'annule plus d'un geste du client : il DEMANDE,
     * la réception INSTRUIT selon la politique d'annulation (P2-SEJ-06).
     */
    public function demanderAnnulation(Request $request, string $reference, DemandesDAnnulation $demandes): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);
        $saisie = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:1000']], [], ['motif' => 'motif']);

        /** @var User $client */
        $client = $request->user();
        $demande = $demandes->demander($sejour, $client, (string) $saisie['motif']);

        return ReponseApi::cree(new DemandeAnnulationResource($demande), 'Demande d’annulation envoyée : la réception va l’instruire.');
    }

    /** Mon espace › Mes paiements : mes reçus, mon avance disponible et ce qu'il me reste à régler en agence (CdC § 5.3). */
    public function paiements(Request $request, Avances $avances, SoldeDesSejours $soldes): JsonResponse
    {
        $moi = $request->user()?->getAuthIdentifier();
        $reglements = Reglement::query()->with(['agence', 'tiers', 'auteur', 'imputations.affaire'])
            ->where('tiers_id', $moi)->where('sens', 'encaissement')->where('etat', EtatDuReglement::Effectue)
            ->orderByDesc('finalise_le')->get();

        // Seuls les séjours réglés « en agence » attendent un passage au guichet : le paiement en ligne
        // et le compte à terme suivent chacun leur propre circuit, jamais celui-ci.
        $aRegler = Sejour::query()->where('client_id', $moi)->where('mode_reglement', 'agence')
            ->whereNotIn('etat', [EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->get()->sum(fn (Sejour $s): int => $soldes->de($s)['reste_du']);

        return ReponseApi::succes([
            'avance_disponible' => $avances->disponible((int) $moi),
            'montant_a_regler_en_agence' => $aRegler,
            'fidelite' => app(PointsDeFidelite::class)->releve((int) $moi),
            'reglements' => $reglements->map(fn (Reglement $r): array => [
                'reference' => $r->reference, 'numero_recu' => $r->numero_recu, 'date' => $r->finalise_le?->format('d/m/Y'),
                'montant' => $r->montant, 'mode' => $r->mode->libelle(),
                'affaires' => $r->imputations->map(fn ($i) => $i->affaire instanceof Sejour ? $i->affaire->reference : null)->filter()->values(),
            ]),
        ]);
    }

    /** Mon reçu, à moi seul. */
    public function recu(Request $request, string $numero, Recus $recus): Response
    {
        $reglement = Reglement::query()->where('numero_recu', $numero)->where('tiers_id', $request->user()?->getAuthIdentifier())->firstOrFail();

        return response($recus->pdf($reglement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$numero.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Le séjour d'un autre client N'EXISTE PAS pour moi : 404, jamais 403. */
    private function leMien(Request $request, string $reference): Sejour
    {
        return Sejour::query()->with(self::RELATIONS)
            ->where('reference', $reference)
            ->where('client_id', $request->user()?->getAuthIdentifier())
            ->firstOrFail();
    }
}
