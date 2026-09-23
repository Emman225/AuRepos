<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Services\PiecesJustificatives;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Occupant;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\CheckIn;
use App\Domain\Sejours\Services\CheckOut;
use App\Domain\Sejours\Services\ClientDeLaReception;
use App\Domain\Sejours\Services\ConfirmationDeSejour;
use App\Domain\Sejours\Services\DeplacementDeSejour;
use App\Domain\Sejours\Services\ProlongationDeSejour;
use App\Domain\Sejours\Services\ReductionSurSejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sejours\ReservationManuelleRequest;
use App\Http\Resources\Backoffice\ChangementAValiderResource;
use App\Http\Resources\Backoffice\SejourResource;
use App\Http\Resources\Sejours\OccupantResource;
use App\Support\Api\ErreurMetier;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** Réservations (CdC § 6.1) : en attente, confirmées, en cours… et la confirmation. */
final class SejoursController extends Controller
{
    private const RELATIONS = ['client', 'logement.residence'];

    public function __construct(
        private readonly ConfirmationDeSejour $confirmation,
        private readonly PerimetreGestionnaire $perimetre,
        private readonly ReductionSurSejour $reductionSurSejour,
        private readonly ClientDeLaReception $clientDeLaReception,
        private readonly ReservationDeSejour $reservation,
        private readonly CheckIn $checkIn,
        private readonly CheckOut $checkOut,
        private readonly ProlongationDeSejour $prolongation,
        private readonly DeplacementDeSejour $deplacement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $colonneDate = $filtres['champ_date'] ?? 'arrivee';
        $page = $this->requeteFiltree($filtres, $request, $colonneDate)->orderBy($colonneDate)->orderBy('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, SejourResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $colonneDate = $filtres['champ_date'] ?? 'arrivee';
        $sejours = $this->requeteFiltree($filtres, $request, $colonneDate)->orderBy($colonneDate)->orderBy('id')->limit(5000)->get();

        $export = new ExportDeListe('Reservations', [
            'reference' => 'Référence', 'client' => 'Client', 'logement' => 'Logement',
            'arrivee' => 'Arrivée', 'depart' => 'Départ', 'etat' => 'État', 'net_a_payer' => 'Net à payer',
        ], $sejours->map(fn (Sejour $s): array => [
            'reference' => $s->reference,
            'client' => $s->client ? $s->client->nomComplet() : '',
            'logement' => $s->logement->nom,
            'arrivee' => $s->arrivee->format('d/m/Y'),
            'depart' => $s->depart->format('d/m/Y'),
            'etat' => $s->etat->libelle(),
            'net_a_payer' => (string) $s->net_a_payer,
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'etat' => ['nullable', Rule::enum(EtatDuSejour::class)],
            'recherche' => ['nullable', 'string', 'max:100'],
            'residence_id' => ['nullable', 'integer'],
            'mode_reglement' => ['nullable', Rule::in(['en_ligne', 'agence', 'a_terme', 'canal_externe'])],
            // Sur quelle date porte le filtre « du … au … » : l'arrivée (par défaut, la réception) ou le départ
            // (« Départs du jour ») — les deux files du CdC § 6.1 partagent les mêmes paramètres de période.
            'champ_date' => ['nullable', Rule::in(['arrivee', 'depart'])],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Sejour>
     */
    private function requeteFiltree(array $filtres, Request $request, string $colonneDate): Builder
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $autorisees = $this->perimetre->residencesAutorisees($utilisateur);

        $requete = Sejour::query()->with(self::RELATIONS)
            ->when($autorisees !== null, fn (Builder $q) => $q->whereHas('logement', fn (Builder $l) => $l->whereIn('residence_id', $autorisees ?? [])))
            ->when($filtres['etat'] ?? null, fn (Builder $q, string $v) => $q->where('etat', $v))
            ->when($filtres['residence_id'] ?? null, fn (Builder $q, int $v) => $q->whereRelation('logement', 'residence_id', $v))
            ->when($filtres['mode_reglement'] ?? null, fn (Builder $q, string $v) => $q->where('mode_reglement', $v))
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $motif = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn (Builder $ou) => $ou->where('reference', 'ilike', $motif)
                    ->orWhereHas('client', fn (Builder $c) => $c->where('nom', 'ilike', $motif)->orWhere('prenoms', 'ilike', $motif)->orWhere('email', 'ilike', $motif)));
            });

        return FiltrePeriode::depuis($request)->appliquer($requete, $colonneDate);
    }

    public function afficher(Sejour $sejour): JsonResponse
    {
        return ReponseApi::succes(new SejourResource($sejour->load(self::RELATIONS)));
    }

    /**
     * Réservation manuelle par la réception (CdC § 6.1) : téléphone, walk-in, canal externe.
     * Un client existant, ou un nouveau créé sur-le-champ, comme pour un partenaire.
     */
    public function creer(ReservationManuelleRequest $request): JsonResponse
    {
        /** @var array{canal: string, client_id?: int|null, client?: array<string, mixed>|null, reference_logement: string, arrivee: string, depart: string, adultes: int, mode_reglement: string} $saisie */
        $saisie = $request->validated();
        $receptionniste = $this->moi($request);

        $logement = Logement::query()->where('reference', $saisie['reference_logement'])->first()
            ?? throw new ErreurMetier('Ce logement n’est pas ouvert à la réservation.', 'logement_non_reservable', 422);

        $autorisees = $this->perimetre->residencesAutorisees($receptionniste);
        if ($autorisees !== null && ! in_array($logement->residence_id, $autorisees, true)) {
            abort(404);
        }

        $client = $this->clientDeLaReception->resoudre(
            isset($saisie['client_id']) ? (int) $saisie['client_id'] : null,
            $saisie['client'] ?? null,
        );

        $sejour = $this->reservation->reserver($client, $logement, $saisie, $saisie['canal'], $receptionniste);

        return ReponseApi::cree(
            new SejourResource($sejour->load(self::RELATIONS)),
            $sejour->expire_le
                ? 'Réservation enregistrée. Réglez-la avant le '.$sejour->expire_le->format('d/m/Y à H:i').' : passé ce délai, les dates seront libérées.'
                : 'Réservation enregistrée.',
        );
    }

    public function confirmer(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate(['agent_accueil_id' => ['nullable', 'integer']], [], ['agent_accueil_id' => 'agent d’accueil']);

        $sejour = $this->confirmation->confirmer($sejour, $this->moi($request), $saisie['agent_accueil_id'] ?? null);

        return ReponseApi::succes(
            new SejourResource($sejour->load(self::RELATIONS)),
            'Séjour confirmé. Le client reçoit son code d’arrivée, l’adresse et les consignes.',
        );
    }

    public function renvoyerLeCode(Sejour $sejour): JsonResponse
    {
        $this->confirmation->renvoyerLeCode($sejour);

        return ReponseApi::succes(null, 'Le code d’arrivée vient d’être renvoyé au client.');
    }

    /** Code verrouillé ou compromis : réservé aux administrateurs. L'ancien code cesse aussitôt de valoir. */
    public function nouveauCode(Request $request, Sejour $sejour): JsonResponse
    {
        if (! $this->moi($request)->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }

        $this->confirmation->emettreUnNouveauCode($sejour);

        return ReponseApi::succes(null, 'Un nouveau code d’arrivée vient d’être envoyé au client. L’ancien ne vaut plus rien.');
    }

    /**
     * Un administrateur saisit un pourcentage ; il n'entrera en vigueur qu'après confirmation
     * du trésorier désigné (CdC § 6.1). Réservé aux administrateurs.
     */
    public function proposerUneReduction(Request $request, Sejour $sejour): JsonResponse
    {
        if (! $this->moi($request)->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }

        $saisie = $request->validate([
            'pourcentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['pourcentage' => 'pourcentage', 'motif' => 'motif']);

        $changement = $this->reductionSurSejour->proposer($sejour, (float) $saisie['pourcentage'], (string) $saisie['motif'], $this->moi($request));

        return ReponseApi::cree(new ChangementAValiderResource($changement), 'Réduction proposée. Elle entrera en vigueur après confirmation du trésorier.');
    }

    /** Check-in (P2-SEJ-01) : l'agent SAISIT le code d'arrivée, jamais ne le lit (CdC § 11). */
    public function checkIn(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'occupants' => ['nullable', 'array'],
            'occupants.*.nom' => ['required_with:occupants', 'string', 'max:100'],
            'occupants.*.prenoms' => ['nullable', 'string', 'max:150'],
            'occupants.*.enfant' => ['nullable', 'boolean'],
            'occupants.*.type_piece' => ['nullable', 'string', 'max:30'],
            'occupants.*.numero_piece' => ['nullable', 'string', 'max:100'],
            'occupants.*.telephone' => ['nullable', 'string', 'max:30'],
        ], [], ['code' => 'code d’arrivée']);

        $sejour = $this->checkIn->effectuer($sejour, $saisie['code'], $this->moi($request), $saisie['occupants'] ?? []);

        return ReponseApi::succes(new SejourResource($sejour->load([...self::RELATIONS, 'occupants'])), 'Check-in effectué : le séjour est arrivé.');
    }

    /** Consommations déjà connues, pour l'écran de check-out (P2-SEJ-04). */
    public function consommations(Sejour $sejour): JsonResponse
    {
        return ReponseApi::succes($this->checkOut->consommations($sejour));
    }

    /** Check-out (P2-SEJ-04) : caution retenue TOUJOURS saisie manuellement et motivée. */
    public function checkOutSejour(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'caution_retenue' => ['required', 'integer', 'min:0'],
            'motif' => ['nullable', 'string', 'min:5', 'max:255'],
        ], [], ['caution_retenue' => 'caution retenue', 'motif' => 'motif']);

        $sejour = $this->checkOut->effectuer($sejour, $this->moi($request), (int) $saisie['caution_retenue'], $saisie['motif'] ?? null);

        return ReponseApi::succes(new SejourResource($sejour->load(self::RELATIONS)), 'Check-out effectué : le séjour est parti.');
    }

    /** Prolongation ou départ anticipé (P2-SEJ-03) : nouvelle date de départ, devis recalculé. */
    public function prolonger(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate(['depart' => ['required', 'date_format:Y-m-d']], [], ['depart' => 'nouvelle date de départ']);

        $sejour = $this->prolongation->modifierLeDepart($sejour, Carbon::parse($saisie['depart']), $this->moi($request));

        return ReponseApi::succes(new SejourResource($sejour->load(self::RELATIONS)), 'Date de départ mise à jour : devis recalculé.');
    }

    /**
     * Déplacement d'un séjour vers un autre logement du même type (P2-PLA-02, planning back-office).
     * Le logement de destination doit rester dans le périmètre du gestionnaire (CdC § 9.5) —
     * comme pour une réservation manuelle (creer()), un logement hors périmètre n'existe pas ici : 404.
     */
    public function deplacer(Request $request, Sejour $sejour): JsonResponse
    {
        $saisie = $request->validate([
            'logement_id' => ['required', 'integer', 'exists:logements,id'],
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['logement_id' => 'logement de destination', 'motif' => 'motif']);

        $destination = Logement::query()->findOrFail((int) $saisie['logement_id']);
        $gestionnaire = $this->moi($request);

        $autorisees = $this->perimetre->residencesAutorisees($gestionnaire);
        if ($autorisees !== null && ! in_array($destination->residence_id, $autorisees, true)) {
            abort(404);
        }

        $sejour = $this->deplacement->deplacer($sejour, $destination, (string) $saisie['motif'], $gestionnaire);

        return ReponseApi::succes(new SejourResource($sejour->load(self::RELATIONS)), 'Séjour déplacé vers un autre logement.');
    }

    /** Fiche de police (P2-SEJ-01) : les occupants déjà connus pour ce séjour. */
    public function occupants(Sejour $sejour): JsonResponse
    {
        return ReponseApi::succes(OccupantResource::collection($sejour->occupants()->with('pieces')->get()));
    }

    /** Photo de la pièce d'identité d'un occupant (fiche de police, P2-SEJ-01) : chiffrée, jamais un second mécanisme. */
    public function deposerLaPieceDUnOccupant(Request $request, Sejour $sejour, Occupant $occupant, PiecesJustificatives $pieces): JsonResponse
    {
        // Un occupant d'un autre séjour N'EXISTE PAS ici : 404, jamais 403.
        if ($occupant->sejour_id !== $sejour->id) {
            abort(404);
        }

        $saisie = $request->validate([
            'fichier' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5 * 1024)],
        ], [], ['fichier' => 'photo de la pièce']);

        $pieces->deposer($occupant, TypeDePiece::PieceIdentite, $saisie['fichier'], null, $this->moi($request));

        return ReponseApi::succes(new OccupantResource($occupant->load('pieces')), 'Pièce d’identité déposée.');
    }

    private function moi(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }
}
