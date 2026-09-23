<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Models\RetenueDeCaution;
use App\Domain\Caisse\Services\Cautions;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ReglementResource;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** Guichet Cautions (P2-CAU-01 à 03, CdC § 6.3 et § 8) : dépôt, restitution, retenue, état des cautions. */
final class CautionsController extends Controller
{
    private const RELATIONS = ['agence', 'tiers', 'auteur', 'validateur', 'porteurDeLaPreuve', 'sejour'];

    public function __construct(private readonly Cautions $cautions) {}

    /** Où en est la caution d'un séjour : déposée, retenue, restituée, encore détenue. */
    public function solde(Sejour $sejour): JsonResponse
    {
        return ReponseApi::succes(['sejour' => ['id' => $sejour->id, 'reference' => $sejour->reference], ...$this->cautions->soldeDe($sejour)]);
    }

    public function deposer(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'mode' => ['required', Rule::in(['especes', 'mobile_money', 'carte', 'virement', 'cheque'])],
            'reference_du_mode' => ['nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['montant' => 'montant', 'mode' => 'mode de règlement', 'notes' => 'notes / observations']);

        /** @var User $caissier */
        $caissier = $request->user();
        $reglement = $this->cautions->deposer(
            $caissier, $sejour, (int) $saisie['montant'], ModeDeReglement::from($saisie['mode']), $saisie['notes'], $saisie['reference_du_mode'] ?? null,
        );

        return ReponseApi::cree($this->presenter($reglement), 'Dépôt de caution saisi. Il ne comptera qu’une fois validé, prouvé et finalisé.');
    }

    public function restituer(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'montant' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'mode' => ['required', Rule::in(['especes', 'mobile_money', 'virement', 'cheque'])],
            'reference_du_mode' => ['nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['montant' => 'montant', 'mode' => 'mode de règlement', 'notes' => 'notes / observations']);

        /** @var User $auteur */
        $auteur = $request->user();
        $reglement = $this->cautions->restituer(
            $auteur, $sejour, isset($saisie['montant']) ? (int) $saisie['montant'] : null, ModeDeReglement::from($saisie['mode']),
            $saisie['notes'], $saisie['reference_du_mode'] ?? null,
        );

        return ReponseApi::cree($this->presenter($reglement), 'Restitution de caution saisie. Elle suit le même circuit de preuve.');
    }

    public function retenir(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'motif' => ['required', 'string', 'min:5', 'max:255'],
            'justificatifs' => ['nullable', 'array', 'max:10'],
            'justificatifs.*' => File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5 * 1024),
        ], [], ['montant' => 'montant', 'motif' => 'motif', 'justificatifs' => 'justificatifs']);

        /** @var User $administrateur */
        $administrateur = $request->user();
        $retenue = $this->cautions->retenir(
            $administrateur, $sejour, (int) $saisie['montant'], $saisie['motif'], $saisie['justificatifs'] ?? [], $administrateur,
        );

        return ReponseApi::cree($this->presenterRetenue($retenue), 'Retenue enregistrée : facture « frais de dégradation / retard » émise.');
    }

    /** P2-CAU-03 : preuve de l'égalité retenue + restituée + détenue = caution totale. */
    public function etat(Request $request): JsonResponse
    {
        $filtres = $request->validate(['residence_id' => ['nullable', 'integer']]);
        $periode = FiltrePeriode::depuis($request);

        return ReponseApi::succes($this->cautions->etatDesCautions(isset($filtres['residence_id']) ? (int) $filtres['residence_id'] : null, $periode->du, $periode->au));
    }

    private function presenter(Reglement $reglement): ReglementResource
    {
        return new ReglementResource($reglement->refresh()->load(self::RELATIONS));
    }

    /** @return array<string, mixed> */
    private function presenterRetenue(RetenueDeCaution $retenue): array
    {
        $retenue->load(['facture', 'auteur', 'pieces']);

        return [
            'id' => $retenue->id,
            'sejour_id' => $retenue->sejour_id,
            'montant' => $retenue->montant,
            'motif' => $retenue->motif,
            'facture' => ['id' => $retenue->facture->id, 'numero' => $retenue->facture->numero],
            'effectuee_par' => $retenue->auteur->nomComplet(),
            'effectuee_le' => $retenue->effectuee_le->format('d/m/Y H:i:s'),
            'justificatifs' => $retenue->pieces->count(),
        ];
    }
}
