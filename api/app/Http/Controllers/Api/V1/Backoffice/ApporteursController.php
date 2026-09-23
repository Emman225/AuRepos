<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Partenaires\Services\CreationDePartenaire;
use App\Domain\Partenaires\Services\Parrainage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partenaires\ApporteurRequest;
use App\Http\Resources\Backoffice\ApporteurResource;
use App\Http\Resources\Backoffice\CommissionApporteurResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Apporteurs d'affaires › liste, fiche, mandat de commission, commissions dues (CdC — apporteurs). */
final class ApporteursController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'actif' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Apporteur::query()
            ->with('utilisateur')
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $motif = '%'.addcslashes($v, '%_\\').'%';
                $q->where('code', 'ilike', $motif)
                    ->orWhereHas('utilisateur', fn (Builder $u) => $u
                        ->where('nom', 'ilike', $motif)->orWhere('prenoms', 'ilike', $motif)->orWhere('email', 'ilike', $motif));
            })
            ->when(array_key_exists('actif', $filtres) && $filtres['actif'] !== null,
                fn (Builder $q) => $q->where('actif', (bool) $filtres['actif']))
            ->orderBy('id')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ApporteurResource::class);
    }

    public function creer(ApporteurRequest $request, CreationDePartenaire $creation): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();

        $apporteur = $creation->apporteur($compte, $fiche);

        return ReponseApi::cree(
            new ApporteurResource($apporteur->load('utilisateur')),
            'Apporteur créé. Un courriel l’invite à choisir son mot de passe.',
        );
    }

    public function modifier(ApporteurRequest $request, Apporteur $apporteur): JsonResponse
    {
        [$compte, $fiche] = $request->compteEtFiche();

        DB::transaction(function () use ($apporteur, $compte, $fiche): void {
            if ($compte !== []) {
                $apporteur->utilisateur->update($compte);
            }
            $apporteur->update($fiche);
        });

        return ReponseApi::succes(new ApporteurResource($apporteur->refresh()->load('utilisateur')), 'Apporteur modifié.');
    }

    /** Ses commissions et ce qui lui reste dû (commissions cumulées − décaissements déjà versés). */
    public function commissions(Apporteur $apporteur, Parrainage $parrainage): JsonResponse
    {
        $commissions = $apporteur->commissions()->with('sejour')->orderByDesc('id')->get();

        return ReponseApi::succes([
            'solde_du' => $parrainage->soldeDu($apporteur),
            'commissions' => CommissionApporteurResource::collection($commissions),
        ]);
    }
}
