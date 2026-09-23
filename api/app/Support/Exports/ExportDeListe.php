<?php

namespace App\Support\Exports;

use App\Support\Api\ErreurMetier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Export générique d'une liste vers Excel, Word ou PDF (CdC § 6.8).
 *
 * Les colonnes et les lignes passées ici sont déjà celles de l'écran : mêmes libellés, mêmes
 * dates au format jj/mm/aaaa hh:mm:ss, mêmes montants déjà mis en forme. Ce service ne connaît
 * aucune règle métier, seulement la mise en page du document — à chaque liste de préparer ses
 * lignes comme elle les affiche.
 *
 * Dans Mon Gravier, chaque export était une vue Blade recopiée et légèrement modifiée par écran ;
 * ici un seul gabarit sert à toutes les listes, et la bascule en A3 paysage au-delà de 12 colonnes
 * (CdC § 6.8) est automatique.
 */
final class ExportDeListe
{
    private const SEUIL_PAYSAGE = 6;

    private const SEUIL_A3 = 12;

    /**
     * @param  array<string, string>  $colonnes  clé de la ligne => libellé affiché, dans l'ordre des colonnes
     * @param  array<int, array<string, string|int|float|null>>  $lignes
     */
    public function __construct(
        private readonly string $titre,
        private readonly array $colonnes,
        private readonly array $lignes,
    ) {}

    public function reponse(string $format): Response
    {
        [$contenu, $type, $extension] = match ($format) {
            'xlsx' => [$this->xlsx(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
            'docx' => [$this->docx(), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
            'pdf' => [$this->pdf(), 'application/pdf', 'pdf'],
            default => throw new ErreurMetier('Format d’export inconnu : choisissez xlsx, docx ou pdf.', 'format_export_inconnu', 422),
        };

        $nom = Str::slug($this->titre).'-'.now()->format('Y-m-d-His').'.'.$extension;

        return response($contenu, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function xlsx(): string
    {
        $classeur = new Spreadsheet;
        $feuille = $classeur->getActiveSheet();
        $feuille->setTitle(Str::limit($this->titre, 31, ''));

        $feuille->fromArray([array_values($this->colonnes)], null, 'A1');
        $feuille->fromArray($this->matrice(), null, 'A2');

        $derniere = Coordinate::stringFromColumnIndex(max(count($this->colonnes), 1));
        $feuille->getStyle('A1:'.$derniere.'1')->getFont()->setBold(true);
        $feuille->getStyle('A1:'.$derniere.'1')->getFont()->getColor()->setRGB('FFFFFF');
        $feuille->getStyle('A1:'.$derniere.'1')->getFill()->setFillType(Fill::FILL_SOLID);
        $feuille->getStyle('A1:'.$derniere.'1')->getFill()->getStartColor()->setRGB('1E3A5F');

        foreach (range(1, max(count($this->colonnes), 1)) as $colonne) {
            $feuille->getColumnDimensionByColumn($colonne)->setAutoSize(true);
        }

        $classeur->setActiveSheetIndex(0);

        $tampon = new Xlsx($classeur);
        ob_start();
        $tampon->save('php://output');

        return (string) ob_get_clean();
    }

    public function docx(): string
    {
        $document = new PhpWord;
        $document->getDocInfo()->setTitle($this->titre);

        $paysage = count($this->colonnes) > self::SEUIL_PAYSAGE;
        $section = $document->addSection($paysage ? ['orientation' => 'landscape'] : []);
        $section->addTitle($this->titre, 1);
        $section->addTextBreak();

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'D9C3A5', 'cellMargin' => 60, 'width' => 100 * 50, 'unit' => 'pct']);

        $table->addRow();
        foreach ($this->colonnes as $libelle) {
            $table->addCell(null, ['bgColor' => '1E3A5F'])->addText($libelle, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
        }

        foreach ($this->lignes as $ligne) {
            $table->addRow();
            foreach (array_keys($this->colonnes) as $cle) {
                $table->addCell()->addText((string) ($ligne[$cle] ?? ''), ['size' => 9]);
            }
        }

        // PhpWord n'écrit pas de manière fiable sur un flux mémoire : fichier temporaire, relu puis effacé.
        $chemin = tempnam(sys_get_temp_dir(), 'export-docx-');
        if ($chemin === false) {
            throw new \RuntimeException('Impossible de créer le fichier temporaire de l’export Word.');
        }

        try {
            IOFactory::createWriter($document, 'Word2007')->save($chemin);

            return (string) file_get_contents($chemin);
        } finally {
            @unlink($chemin);
        }
    }

    public function pdf(): string
    {
        $nombreDeColonnes = count($this->colonnes);
        $taille = $nombreDeColonnes > self::SEUIL_A3 ? 'a3' : 'a4';
        $orientation = $nombreDeColonnes > self::SEUIL_PAYSAGE ? 'landscape' : 'portrait';

        return Pdf::loadView('pdf.export-liste', [
            'titre' => $this->titre,
            'colonnes' => $this->colonnes,
            'lignes' => $this->lignes,
        ])->setPaper($taille, $orientation)->output();
    }

    /** @return array<int, array<int, string|int|float>> */
    private function matrice(): array
    {
        $cles = array_keys($this->colonnes);

        return array_map(
            fn (array $ligne): array => array_map(fn (string $cle): string|int|float => $ligne[$cle] ?? '', $cles),
            $this->lignes,
        );
    }
}
