<?php

namespace App\Domain\Comptabilite\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Partenaires\Models\CommissionApporteur;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Enums\StatutDemandeATerme;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Grands livres par catégorie de tiers (CdC § 9.3) : clients ordinaires, clients à terme,
 * propriétaires, restaurateurs, chauffeurs, livreurs, apporteurs, agents. Chaque ligne
 * additionne ce qui est déjà enregistré pour ce tiers ; les catégories « restaurateurs »,
 * « chauffeurs », « livreurs » et « agents » n'ont, à ce jour, que des décaissements
 * possibles (aucune dette générée n'est encore modélisée pour elles), donc seule la colonne
 * « versé » y est renseignée — documenté dans la réponse plutôt que deviné.
 */
final class GrandsLivres
{
    public const CATEGORIES = [
        'clients_ordinaires', 'clients_a_terme', 'proprietaires',
        'restaurateurs', 'chauffeurs', 'livreurs', 'apporteurs', 'agents',
    ];

    public function __construct(private readonly SoldeDesSejours $soldes) {}

    public function parCategorie(string $categorie, ?Carbon $du, ?Carbon $au): array
    {
        return match ($categorie) {
            'clients_ordinaires' => $this->clients($du, $au, aTerme: false),
            'clients_a_terme' => $this->clients($du, $au, aTerme: true),
            'proprietaires' => $this->partenaires(Profil::Proprietaire, $du, $au),
            'restaurateurs' => $this->partenaires(Profil::Restaurateur, $du, $au),
            'chauffeurs' => $this->partenaires(Profil::Chauffeur, $du, $au),
            'livreurs' => $this->partenaires(Profil::Livreur, $du, $au),
            'apporteurs' => $this->apporteurs($du, $au),
            'agents' => $this->agents($du, $au),
            default => throw new ErreurMetier(
                'Catégorie de tiers inconnue : '.implode(', ', self::CATEGORIES).'.', 'categorie_grand_livre_inconnue', 422,
            ),
        };
    }

    private function clients(?Carbon $du, ?Carbon $au, bool $aTerme): array
    {
        $idsATerme = Client::query()->where('statut_a_terme', StatutDemandeATerme::Acceptee)->pluck('user_id')->all();

        $sejours = Sejour::query()->with('client')
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->when($du, fn ($q) => $q->whereDate('arrivee', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('arrivee', '<=', $au))
            ->get()
            ->filter(fn (Sejour $s) => in_array($s->client_id, $idsATerme, true) === $aTerme);

        $lignes = $sejours->groupBy('client_id')->map(function (Collection $g) {
            /** @var Sejour $premier */
            $premier = $g->first();
            $du = (int) $g->sum(fn (Sejour $s) => $this->soldes->de($s)['reste_du']);
            $verse = (int) $g->sum(fn (Sejour $s) => $this->soldes->de($s)['encaisse']);

            return ['tiers' => $premier->client?->nomComplet() ?? 'Client n° '.$premier->client_id, 'du' => $du, 'verse' => $verse];
        })->values();

        return ['lignes' => $lignes->all(), 'total_du' => (int) $lignes->sum('du'), 'total_verse' => (int) $lignes->sum('verse')];
    }

    private function partenaires(Profil $profil, ?Carbon $du, ?Carbon $au): array
    {
        $decaissements = Reglement::query()->with('tiers')
            ->where('sens', 'decaissement')->where('guichet', Guichet::DettesPartenaires)
            ->where('etat', EtatDuReglement::Effectue)
            ->when($du, fn ($q) => $q->whereDate('saisi_le', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('saisi_le', '<=', $au))
            ->get()
            ->filter(fn (Reglement $r) => $r->tiers?->profil === $profil);

        $lignes = $decaissements->groupBy('tiers_id')->map(function (Collection $g) {
            /** @var Reglement $premier */
            $premier = $g->first();

            return ['tiers' => $premier->tiers->nomComplet(), 'verse' => (int) $g->sum('montant')];
        })->values();

        return ['lignes' => $lignes->all(), 'total_verse' => (int) $lignes->sum('verse')];
    }

    private function apporteurs(?Carbon $du, ?Carbon $au): array
    {
        $decaissesParTiers = Reglement::query()
            ->where('sens', 'decaissement')->where('guichet', Guichet::DettesPartenaires)->where('etat', EtatDuReglement::Effectue)
            ->selectRaw('tiers_id, sum(montant) as total')->groupBy('tiers_id')->pluck('total', 'tiers_id');

        $lignes = Apporteur::query()->with('utilisateur')->get()->map(function (Apporteur $apporteur) use ($du, $au, $decaissesParTiers): array {
            $commissions = (int) CommissionApporteur::query()->where('apporteur_id', $apporteur->id)
                ->when($du, fn ($q) => $q->whereDate('created_at', '>=', $du))
                ->when($au, fn ($q) => $q->whereDate('created_at', '<=', $au))
                ->sum('montant');
            $verse = (int) ($decaissesParTiers[$apporteur->user_id] ?? 0);

            return ['tiers' => $apporteur->nomAffiche(), 'du' => max(0, $commissions - $verse), 'verse' => $verse];
        })->filter(fn (array $l) => $l['du'] > 0 || $l['verse'] > 0)->values();

        return ['lignes' => $lignes->all(), 'total_du' => (int) $lignes->sum('du'), 'total_verse' => (int) $lignes->sum('verse')];
    }

    private function agents(?Carbon $du, ?Carbon $au): array
    {
        $decaissements = Reglement::query()->with('tiers')
            ->where('sens', 'decaissement')->where('guichet', Guichet::DettesPartenaires)->where('etat', EtatDuReglement::Effectue)
            ->when($du, fn ($q) => $q->whereDate('saisi_le', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('saisi_le', '<=', $au))
            ->get()
            ->filter(fn (Reglement $r) => in_array($r->tiers?->profil, [Profil::AgentAssistance, Profil::AgentTerrain], true));

        $lignes = $decaissements->groupBy('tiers_id')->map(function (Collection $g): array {
            /** @var Reglement $premier */
            $premier = $g->first();

            return ['tiers' => $premier->tiers->nomComplet(), 'verse' => (int) $g->sum('montant')];
        })->values();

        return ['lignes' => $lignes->all(), 'total_verse' => (int) $lignes->sum('verse')];
    }
}
