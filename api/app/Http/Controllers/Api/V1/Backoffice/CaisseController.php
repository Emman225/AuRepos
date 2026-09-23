<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Caisse\Services\Recus;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ReglementResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\Response;

/** Caisse : guichets d'encaissement, décaissements et circuit de preuve (CdC § 8). */
final class CaisseController extends Controller
{
    private const RELATIONS = ['agence', 'tiers', 'auteur', 'validateur', 'porteurDeLaPreuve', 'imputations.affaire'];

    public function __construct(private readonly Caisse $caisse) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'sens' => ['nullable', Rule::in(['encaissement', 'decaissement'])],
            'etat' => ['nullable', Rule::enum(EtatDuReglement::class)],
            'guichet' => ['nullable', Rule::enum(Guichet::class)],
            'tiers_id' => ['nullable', 'integer'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $requete = Reglement::query()->with(self::RELATIONS)
            ->when($filtres['sens'] ?? null, fn (Builder $q, string $v) => $q->where('sens', $v))
            ->when($filtres['etat'] ?? null, fn (Builder $q, string $v) => $q->where('etat', $v))
            ->when($filtres['guichet'] ?? null, fn (Builder $q, string $v) => $q->where('guichet', $v))
            ->when($filtres['tiers_id'] ?? null, fn (Builder $q, int $v) => $q->where('tiers_id', $v));

        $page = FiltrePeriode::depuis($request)->appliquer($requete, 'saisi_le')->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ReglementResource::class);
    }

    /** Export Excel, Word ou PDF de la liste, avec les mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $request->validate([
            'sens' => ['nullable', Rule::in(['encaissement', 'decaissement'])],
            'etat' => ['nullable', Rule::enum(EtatDuReglement::class)],
            'guichet' => ['nullable', Rule::enum(Guichet::class)],
            'tiers_id' => ['nullable', 'integer'],
            'format' => ['required', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);

        $requete = Reglement::query()->with(self::RELATIONS)
            ->when($filtres['sens'] ?? null, fn (Builder $q, string $v) => $q->where('sens', $v))
            ->when($filtres['etat'] ?? null, fn (Builder $q, string $v) => $q->where('etat', $v))
            ->when($filtres['guichet'] ?? null, fn (Builder $q, string $v) => $q->where('guichet', $v))
            ->when($filtres['tiers_id'] ?? null, fn (Builder $q, int $v) => $q->where('tiers_id', $v));

        // Pas de pagination ici : c'est tout ce qui correspond aux filtres, plafonné pour rester raisonnable.
        $reglements = FiltrePeriode::depuis($request)->appliquer($requete, 'saisi_le')->orderByDesc('id')->limit(5000)->get();

        $export = new ExportDeListe('Caisse', [
            'reference' => 'Référence', 'sens' => 'Sens', 'guichet' => 'Guichet', 'agence' => 'Agence',
            'tiers' => 'Tiers', 'montant' => 'Montant', 'mode' => 'Mode', 'etat' => 'État',
            'numero_recu' => 'N° reçu', 'saisi_le' => 'Saisi le',
        ], $reglements->map(fn (Reglement $r): array => [
            'reference' => $r->reference,
            'sens' => $r->sens === 'encaissement' ? 'Encaissement' : 'Décaissement',
            'guichet' => $r->guichet->libelle(),
            'agence' => $r->agence->nom,
            'tiers' => $r->tiers->nomComplet(),
            'montant' => number_format($r->montant, 0, ',', ' ').' F',
            'mode' => $r->mode->libelle(),
            'etat' => $r->etat->libelle(),
            'numero_recu' => $r->numero_recu ?? '',
            'saisi_le' => $r->saisi_le->format('d/m/Y H:i:s'),
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** Les affaires d'UN client, avec leur reste dû : ce que le caissier peut cocher ensemble. */
    public function affairesDuClient(User $client, SoldeDesSejours $soldes): JsonResponse
    {
        $sejours = Sejour::query()->with('logement')
            ->where('client_id', $client->id)
            ->whereNotIn('etat', [EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (Sejour $s): array => [
                'id' => $s->id, 'reference' => $s->reference, 'etat' => $s->etat->libelle(), 'logement' => $s->logement->nom,
                'arrivee' => $s->arrivee->format('d/m/Y'), 'depart' => $s->depart->format('d/m/Y'),
                'acompte_exige' => $s->acompte_exige, ...$soldes->de($s),
            ])
            ->filter(fn (array $s) => $s['reste_du'] > 0 || $s['en_cours'] > 0)->values();

        return ReponseApi::succes(['client' => ['id' => $client->id, 'nom' => $client->nomComplet()], 'affaires' => $sejours]);
    }

    public function encaisser(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'sejours' => ['required', 'array', 'min:1', 'max:50'],
            'sejours.*' => ['integer', 'distinct'],
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'mode' => ['required', Rule::in(['especes', 'mobile_money', 'carte', 'virement', 'cheque'])],
            'reference_du_mode' => ['nullable', 'string', 'max:100'],
            // Le champ « Notes / Observations » est obligatoire (CdC § 8.1).
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
            'guichet' => ['nullable', Rule::in([Guichet::Sejours->value, Guichet::CreancesATerme->value])],
            // « Un montant supérieur au reste dû propose “Enregistrer le surplus comme avance” » (CdC § 8.2).
            'surplus_en_avance' => ['nullable', 'boolean'],
        ], [], ['client_id' => 'client', 'sejours' => 'affaires', 'montant' => 'montant', 'mode' => 'mode de règlement', 'notes' => 'notes / observations']);

        /** @var User $caissier */
        $caissier = $request->user();
        $reglement = $this->caisse->saisirUnEncaissement(
            $caissier, User::findOrFail($saisie['client_id']), array_values(array_map('intval', $saisie['sejours'])), (int) $saisie['montant'],
            ModeDeReglement::from($saisie['mode']), $saisie['notes'], Guichet::from($saisie['guichet'] ?? Guichet::Sejours->value),
            $saisie['reference_du_mode'] ?? null, (bool) ($saisie['surplus_en_avance'] ?? false),
        );

        return ReponseApi::cree($this->presenter($reglement), 'Encaissement saisi. Il ne comptera qu’une fois validé, prouvé et finalisé.');
    }

    public function decaisser(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'beneficiaire_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'mode' => ['required', Rule::in(['especes', 'mobile_money', 'virement', 'cheque'])],
            'reference_du_mode' => ['nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['beneficiaire_id' => 'bénéficiaire', 'montant' => 'montant', 'mode' => 'mode de règlement', 'notes' => 'notes / observations']);

        /** @var User $auteur */
        $auteur = $request->user();
        $reglement = $this->caisse->saisirUnDecaissement(
            $auteur, User::findOrFail($saisie['beneficiaire_id']), (int) $saisie['montant'], ModeDeReglement::from($saisie['mode']),
            $saisie['notes'], Guichet::DettesPartenaires, $saisie['reference_du_mode'] ?? null,
        );

        return ReponseApi::cree($this->presenter($reglement), 'Décaissement saisi. Il suit le même circuit de preuve.');
    }

    public function valider(Request $request, Reglement $reglement): JsonResponse
    {
        $this->caisse->valider($reglement, $this->moi($request));

        return ReponseApi::succes($this->presenter($reglement), 'Règlement validé : à payer.');
    }

    public function joindreLaPreuve(Request $request, Reglement $reglement): JsonResponse
    {
        $saisie = $request->validate(
            ['justificatif' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)]], [], ['justificatif' => 'justificatif'],
        );
        $this->caisse->joindreLaPreuve($reglement, $this->moi($request), $saisie['justificatif']);

        return ReponseApi::succes($this->presenter($reglement), 'Preuve jointe. Il reste à finaliser.');
    }

    public function finaliser(Request $request, Reglement $reglement): JsonResponse
    {
        $this->caisse->finaliser($reglement, $this->moi($request));

        return ReponseApi::succes($this->presenter($reglement), 'Règlement effectué.');
    }

    public function rejeter(Request $request, Reglement $reglement): JsonResponse
    {
        $saisie = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:255']], [], ['motif' => 'motif']);
        $this->caisse->rejeter($reglement, $this->moi($request), $saisie['motif']);

        return ReponseApi::succes($this->presenter($reglement), 'Règlement rejeté.');
    }

    public function preuve(Reglement $reglement, JournalAudit $journal): Response
    {
        abort_if($reglement->preuve_chemin === null, 404);
        $journal->consigner('consultation_preuve', 'Consultation de la preuve : '.$reglement->libelleAudit().'.', $reglement);

        return response($this->caisse->contenuDeLaPreuve($reglement), 200, [
            'Content-Type' => (string) $reglement->preuve_mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes((string) $reglement->preuve_nom, '"\\').'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Le reçu tel qu'émis. Il n'existe qu'une fois le règlement effectué (CdC § 8.4). */
    public function recu(Reglement $reglement, Recus $recus): Response
    {
        return response($recus->pdf($reglement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$reglement->numero_recu.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Renvoi manuel : « un envoi manqué ne bloque jamais l'opération » (CdC § 8.4). */
    public function renvoyerLeRecu(Reglement $reglement, Recus $recus): JsonResponse
    {
        $parti = $recus->envoyer($reglement);

        return ReponseApi::succes(
            ['envoye' => $parti],
            $parti ? 'Reçu renvoyé au client.' : 'Le reçu n’a pas pu être envoyé ; l’incident est journalisé. Réessayez plus tard.',
        );
    }

    private function moi(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }

    private function presenter(Reglement $reglement): ReglementResource
    {
        return new ReglementResource($reglement->refresh()->load(self::RELATIONS));
    }
}
