<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptabilite\Services\ControleDeCoherence;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;

/**
 * Contrôle de cohérence (CdC § 9.3, P3-CPT-04) : signale les huit recoupements du cahier des
 * charges, ne corrige jamais rien.
 */
final class ComptabiliteControleController extends Controller
{
    public function __construct(private readonly ControleDeCoherence $controle) {}

    public function index(): JsonResponse
    {
        $anomalies = $this->controle->executer();

        return ReponseApi::succes([
            'anomalies' => $anomalies,
            'nombre_total' => array_sum(array_map('count', $anomalies)),
        ]);
    }
}
