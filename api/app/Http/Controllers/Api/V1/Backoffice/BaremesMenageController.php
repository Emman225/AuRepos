<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Exploitation\Models\BaremeMenage;
use App\Domain\Exploitation\Services\BaremesMenage;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\BaremeMenageResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Barème de ménage (P2-MEN-04, CdC § 6.4) : forfait par type de logement, plancher par mission. Administrateurs seulement. */
final class BaremesMenageController extends Controller
{
    public function __construct(private readonly BaremesMenage $baremes) {}

    public function index(): JsonResponse
    {
        $baremes = BaremeMenage::query()->with('typeLogement')->orderBy('type_logement_id')->get();

        return ReponseApi::succes(BaremeMenageResource::collection($baremes));
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'type_logement_id' => ['required', 'integer', Rule::exists('types_logement', 'id'), Rule::unique('baremes_menage')],
            'forfait' => ['required', 'integer', 'min:0', 'max:100000000'],
            'plancher' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [], ['type_logement_id' => 'type de logement', 'forfait' => 'forfait', 'plancher' => 'plancher']);

        $type = TypeLogement::query()->findOrFail($saisie['type_logement_id']);
        $bareme = $this->baremes->creer($type, (int) $saisie['forfait'], (int) $saisie['plancher']);

        return ReponseApi::cree(new BaremeMenageResource($bareme->load('typeLogement')), 'Barème créé.');
    }

    public function modifier(Request $request, BaremeMenage $bareme): JsonResponse
    {
        $saisie = $request->validate([
            'forfait' => ['required', 'integer', 'min:0', 'max:100000000'],
            'plancher' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [], ['forfait' => 'forfait', 'plancher' => 'plancher']);

        $bareme = $this->baremes->modifier($bareme, (int) $saisie['forfait'], (int) $saisie['plancher']);

        return ReponseApi::succes(new BaremeMenageResource($bareme->load('typeLogement')), 'Barème modifié.');
    }
}
