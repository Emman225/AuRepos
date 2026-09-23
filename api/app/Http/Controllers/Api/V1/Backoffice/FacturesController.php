<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Enums\StatutDeTransmissionFne;
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Fiscalite\Services\Factures;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\FactureResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Factures normalisées électroniques (FNE, DGI — CdC § 9.4). */
final class FacturesController extends Controller
{
    private const RELATIONS = ['sejour', 'client', 'factureOrigine', 'transmetteur'];

    public function __construct(private readonly Factures $factures) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, FactureResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $factures = $this->requeteFiltree($filtres)->orderByDesc('id')->limit(5000)->get();

        $export = new ExportDeListe('Factures', [
            'numero' => 'Numéro', 'type' => 'Type', 'sejour' => 'Séjour', 'client' => 'Client',
            'montant_ttc' => 'Montant TTC', 'statut' => 'Transmission', 'reference_dgi' => 'Référence DGI',
        ], $factures->map(fn (Facture $f): array => [
            'numero' => $f->numero,
            'type' => $f->type->libelle(),
            'sejour' => $f->sejour->reference,
            'client' => $f->client->nomComplet(),
            'montant_ttc' => number_format(abs($f->montant_ttc), 0, ',', ' ').' F',
            'statut' => $f->statut_transmission->libelle(),
            'reference_dgi' => $f->reference_dgi ?? '',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'statut_transmission' => ['nullable', Rule::enum(StatutDeTransmissionFne::class)],
            'type' => ['nullable', Rule::enum(TypeDeFacture::class)],
            'sejour_id' => ['nullable', 'integer'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Facture>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return Facture::query()->with(self::RELATIONS)
            ->when($filtres['statut_transmission'] ?? null, fn (Builder $q, string $v) => $q->where('statut_transmission', $v))
            ->when($filtres['type'] ?? null, fn (Builder $q, string $v) => $q->where('type', $v))
            ->when($filtres['sejour_id'] ?? null, fn (Builder $q, int $v) => $q->where('sejour_id', $v));
    }

    public function afficher(Facture $facture): JsonResponse
    {
        return ReponseApi::succes(new FactureResource($facture->load(self::RELATIONS)));
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'sejour_id' => ['required', 'integer', Rule::exists('sejours', 'id')],
            'type' => ['required', Rule::in([TypeDeFacture::Proforma->value, TypeDeFacture::Facture->value])],
        ]);

        $sejour = Sejour::findOrFail($saisie['sejour_id']);
        /** @var User $auteur */
        $auteur = $request->user();
        $facture = $this->factures->genererPourUnSejour($sejour, TypeDeFacture::from($saisie['type']), $auteur);

        return ReponseApi::cree(new FactureResource($facture->load(self::RELATIONS)), 'Document créé.');
    }

    public function transmettre(Request $request, Facture $facture): JsonResponse
    {
        $this->exigerUnAdministrateur($request);

        $facture = $this->factures->transmettre($facture, $this->administrateur($request));

        return ReponseApi::succes(new FactureResource($facture->load(self::RELATIONS)), 'Document transmis à la DGI.');
    }

    public function avoir(Request $request, Facture $facture): JsonResponse
    {
        $this->exigerUnAdministrateur($request);

        $saisie = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:255']]);
        $avoir = $this->factures->emettreUnAvoir($facture, $saisie['motif'], $this->administrateur($request));

        return ReponseApi::cree(new FactureResource($avoir->load(self::RELATIONS)), 'Avoir émis et transmis à la DGI.');
    }

    public function pdf(Facture $facture): Response
    {
        return response($this->factures->pdf($facture), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$facture->numero.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Transmettre et émettre un avoir engagent la DGI : réservé à un administrateur, comme la caisse (CdC § 8.1). */
    private function exigerUnAdministrateur(Request $request): void
    {
        if (! $this->administrateur($request)->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }
    }

    private function administrateur(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }
}
