<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptabilite\Services\ExportComptable;
use App\Http\Controllers\Controller;
use App\Support\Exports\ExportDeListe;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Export des écritures comptables vers Sage ou Excel (CdC § 9.3, P3-CPT-06). */
final class ComptabiliteExportController extends Controller
{
    public function __construct(private readonly ExportComptable $export) {}

    public function exporter(Request $request): Response
    {
        $filtres = $request->validate(['format' => ['required', Rule::in(['sage', 'xlsx'])]]);
        $periode = FiltrePeriode::depuis($request);
        $du = $periode->du ?? Carbon::today()->startOfMonth();
        $au = $periode->au ?? Carbon::today()->endOfMonth();

        $ecritures = $this->export->genererEcritures($du, $au);

        return $filtres['format'] === 'sage' ? $this->sage($ecritures) : $this->xlsx($ecritures);
    }

    /** @param  list<array<string, mixed>>  $ecritures */
    private function xlsx(array $ecritures): Response
    {
        $export = new ExportDeListe('Écritures comptables', [
            'date' => 'Date', 'journal' => 'Journal', 'compte' => 'Compte', 'libelle' => 'Libellé',
            'debit' => 'Débit', 'credit' => 'Crédit', 'piece' => 'Pièce',
        ], $ecritures);

        return $export->reponse('xlsx');
    }

    /**
     * Format Sage (import « Écritures comptables ») : CSV point-virgule, une ligne par écriture,
     * montants en centimes sans séparateur de milliers (attente courante des imports Sage).
     *
     * @param  list<array<string, mixed>>  $ecritures
     */
    private function sage(array $ecritures): Response
    {
        $lignes = ['JournalCode;Date;CompteNum;CompteLib;Debit;Credit;PieceRef'];
        foreach ($ecritures as $e) {
            $lignes[] = implode(';', [
                $e['journal'], Carbon::parse($e['date'])->format('Ymd'), $e['compte'],
                str_replace(';', ',', (string) $e['libelle']), (string) $e['debit'], (string) $e['credit'], $e['piece'],
            ]);
        }

        $contenu = implode("\r\n", $lignes)."\r\n";
        $nom = 'ecritures-comptables-'.now()->format('Y-m-d-His').'.csv';

        return response($contenu, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
