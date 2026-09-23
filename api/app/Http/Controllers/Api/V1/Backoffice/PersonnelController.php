<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Comptes\Services\ComptesDuPersonnel;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\PersonnelResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Comptes du personnel : administrateurs, gestionnaires, gouvernantes, agents d'assistance (CdC § 9.5). */
final class PersonnelController extends Controller
{
    private const PROFILS_PERSONNEL = [
        Profil::SuperAdministrateur->value, Profil::Administrateur->value,
        Profil::Gestionnaire->value, Profil::Gouvernante->value, Profil::AgentAssistance->value,
    ];

    public function __construct(private readonly ComptesDuPersonnel $comptes) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderBy('nom')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, PersonnelResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $personnel = $this->requeteFiltree($filtres)->orderBy('nom')->limit(5000)->get();

        $export = new ExportDeListe('Personnel', [
            'nom' => 'Nom', 'email' => 'Courriel', 'identifiant' => 'Identifiant',
            'profil' => 'Profil', 'agence' => 'Agence', 'statut' => 'Statut',
        ], $personnel->map(fn (User $u): array => [
            'nom' => $u->nomComplet(),
            'email' => $u->email,
            'identifiant' => $u->identifiant ?? '',
            'profil' => $u->profil->libelle(),
            'agence' => $u->agence ? $u->agence->nom : '',
            'statut' => $u->statut->libelle(),
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'profil' => ['nullable', Rule::in(self::PROFILS_PERSONNEL)],
            'agence_id' => ['nullable', 'integer'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<User>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return User::query()->whereIn('profil', self::PROFILS_PERSONNEL)->with(['agence', 'residences'])
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $terme = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn (Builder $q2) => $q2->where('nom', 'ilike', $terme)->orWhere('email', 'ilike', $terme)->orWhere('identifiant', 'ilike', $terme));
            })
            ->when($filtres['profil'] ?? null, fn (Builder $q, string $v) => $q->where('profil', $v))
            ->when($filtres['agence_id'] ?? null, fn (Builder $q, int $v) => $q->where('agence_id', $v));
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'telephone' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('users', 'telephone')],
            'profil' => ['required', Rule::in(self::PROFILS_PERSONNEL)],
            'agence_id' => ['nullable', 'integer', Rule::exists('agences', 'id')],
            'residences' => ['nullable', 'array'],
            'residences.*' => ['integer', Rule::exists('residences', 'id')],
        ], [], [
            'nom' => 'nom', 'prenoms' => 'prénoms', 'email' => 'courriel', 'telephone' => 'téléphone',
            'profil' => 'profil', 'agence_id' => 'agence', 'residences' => 'résidences',
        ]);

        /** @var User $auteur */
        $auteur = $request->user();
        $utilisateur = $this->comptes->creer($auteur, $saisie);

        return ReponseApi::cree(new PersonnelResource($utilisateur), 'Compte créé. L’identifiant de connexion a été envoyé par courriel.');
    }

    public function rattacherAuxResidences(Request $request, User $utilisateur): JsonResponse
    {
        $saisie = $request->validate([
            'residences' => ['required', 'array'],
            'residences.*' => ['integer', Rule::exists('residences', 'id')],
        ], [], ['residences' => 'résidences']);

        $utilisateur = $this->comptes->rattacherAuxResidences($utilisateur, $saisie['residences']);

        return ReponseApi::succes(new PersonnelResource($utilisateur), 'Résidences mises à jour.');
    }
}
