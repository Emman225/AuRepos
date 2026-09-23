<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\ChargeProprietaire;
use App\Domain\Partenaires\Models\RelevePropretaire;
use App\Domain\Partenaires\Services\DetteProprietaire;
use App\Domain\Partenaires\Services\RelevesProprietaires;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Propriétaires › Dette, charges refacturées et relevés (back office, CdC § 7.2, P3-PRO-02/03).
 * Guichet « Dettes partenaires » : ce que l'entreprise doit à CE propriétaire, ce qui a été
 * versé, les charges qui viendront en déduction de son prochain relevé.
 */
final class DetteProprietaireController extends Controller
{
    public function __construct(
        private readonly DetteProprietaire $dette,
        private readonly RelevesProprietaires $releves,
    ) {}

    public function afficher(Proprietaire $proprietaire): JsonResponse
    {
        $paiements = Reglement::query()
            ->where('tiers_id', $proprietaire->user_id)->where('sens', 'decaissement')->where('etat', EtatDuReglement::Effectue)
            ->orderByDesc('finalise_le')->get();

        $charges = ChargeProprietaire::query()->where('proprietaire_id', $proprietaire->id)->whereNull('releve_id')
            ->orderByDesc('periode')->get();

        return ReponseApi::succes([
            ...$this->dette->soldeDu($proprietaire),
            'charges_non_reprises' => $charges->map(fn (ChargeProprietaire $c): array => [
                'id' => $c->id, 'periode' => $c->periode->format('m/Y'), 'nature' => ChargeProprietaire::NATURES[$c->nature] ?? $c->nature,
                'montant' => $c->montant, 'motif' => $c->motif,
            ]),
            'paiements' => $paiements->map(fn (Reglement $r): array => [
                'reference' => $r->reference, 'montant' => $r->montant, 'mode' => $r->mode->libelle(), 'date' => $r->finalise_le?->format('d/m/Y'),
            ]),
        ]);
    }

    /** Charge refacturée (ménage, réparation...) : déduite du relevé du mois qu'elle indique (CdC § 7.2). */
    public function ajouterUneCharge(Request $request, Proprietaire $proprietaire): JsonResponse
    {
        $saisie = $request->validate([
            'periode' => ['required', 'date_format:Y-m'],
            'nature' => ['required', Rule::in(array_keys(ChargeProprietaire::NATURES))],
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
            'motif' => ['required', 'string', 'min:5', 'max:255'],
            'logement_id' => ['nullable', 'integer', Rule::exists('logements', 'id')],
        ], [], ['periode' => 'période', 'nature' => 'nature', 'montant' => 'montant', 'motif' => 'motif', 'logement_id' => 'logement']);

        /** @var User $auteur */
        $auteur = $request->user();
        $charge = ChargeProprietaire::create([
            'proprietaire_id' => $proprietaire->id, 'logement_id' => $saisie['logement_id'] ?? null,
            'periode' => Carbon::createFromFormat('Y-m', $saisie['periode'])->startOfMonth(),
            'nature' => $saisie['nature'], 'montant' => $saisie['montant'], 'motif' => $saisie['motif'], 'cree_par' => $auteur->id,
        ]);

        return ReponseApi::cree(['id' => $charge->id], 'Charge enregistrée : elle sera déduite du relevé de sa période.');
    }

    public function relevesDuProprietaire(Proprietaire $proprietaire): JsonResponse
    {
        $releves = RelevePropretaire::query()->where('proprietaire_id', $proprietaire->id)->orderByDesc('periode')->get();

        return ReponseApi::succes($releves->map(fn (RelevePropretaire $r): array => [
            'id' => $r->id, 'periode' => $r->periode->format('m/Y'), 'nuitees_consommees' => $r->nuitees_consommees,
            'montant_brut' => $r->montant_brut, 'montant_net' => $r->montant_net, 'envoye_le' => $r->envoye_le?->format('d/m/Y H:i'),
        ]));
    }

    /** Génération manuelle, en plus de la génération planifiée (`proprietaires:generer-releves`). */
    public function genererLeReleve(Request $request, Proprietaire $proprietaire): JsonResponse
    {
        $saisie = $request->validate(['periode' => ['required', 'date_format:Y-m']], [], ['periode' => 'période']);

        /** @var User $auteur */
        $auteur = $request->user();
        $releve = $this->releves->genererPourLeMois($proprietaire, Carbon::createFromFormat('Y-m', $saisie['periode']), $auteur);
        $this->releves->envoyerUneFois($releve);

        return ReponseApi::cree(['id' => $releve->id, 'montant_net' => $releve->montant_net], 'Relevé généré et envoyé.');
    }
}
