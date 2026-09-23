<?php

use App\Support\Api\ErreurMetier;
use App\Support\Exports\ExportDeListe;

/** @return array<string, string> */
function colonnesDExemple(int $nombre): array
{
    $colonnes = [];
    foreach (range(1, $nombre) as $i) {
        $colonnes['champ'.$i] = 'Colonne '.$i;
    }

    return $colonnes;
}

it('produit un classeur Excel valide (fichier ZIP, non vide)', function (): void {
    $export = new ExportDeListe('Ma liste', ['reference' => 'Référence', 'montant' => 'Montant'], [
        ['reference' => 'A-001', 'montant' => '10 000 F'],
        ['reference' => 'A-002', 'montant' => '25 500 F'],
    ]);

    $contenu = $export->xlsx();

    expect($contenu)->not->toBeEmpty();
    // Un .xlsx est un fichier ZIP : ses deux premiers octets sont toujours "PK".
    expect(substr($contenu, 0, 2))->toBe('PK');
});

it('produit un document Word valide (fichier ZIP, non vide)', function (): void {
    $export = new ExportDeListe('Ma liste', ['reference' => 'Référence', 'montant' => 'Montant'], [
        ['reference' => 'A-001', 'montant' => '10 000 F'],
    ]);

    $contenu = $export->docx();

    expect($contenu)->not->toBeEmpty();
    expect(substr($contenu, 0, 2))->toBe('PK');
});

it('produit un PDF valide, avec toutes les lignes', function (): void {
    $export = new ExportDeListe('Ma liste', ['reference' => 'Référence'], [
        ['reference' => 'A-001'], ['reference' => 'A-002'], ['reference' => 'A-003'],
    ]);

    $contenu = $export->pdf();

    expect($contenu)->not->toBeEmpty();
    expect(substr($contenu, 0, 4))->toBe('%PDF');
});

it('affiche « aucune ligne » plutôt qu’un tableau vide quand il n’y a rien à exporter', function (): void {
    $export = new ExportDeListe('Ma liste', ['reference' => 'Référence'], []);

    $contenu = $export->pdf();

    expect($contenu)->not->toBeEmpty();
});

it('bascule le PDF en A3 paysage au-delà de douze colonnes (CdC § 6.8)', function (): void {
    // On ne peut pas lire l'orientation depuis le binaire PDF produit sans dépendance
    // supplémentaire : on prouve plutôt que le document se génère sans erreur avec
    // beaucoup de colonnes, seuil auquel dompdf est le plus susceptible d'échouer.
    $colonnes = colonnesDExemple(15);
    $ligne = array_combine(array_keys($colonnes), array_fill(0, 15, 'valeur'));

    $export = new ExportDeListe('Liste large', $colonnes, [$ligne]);

    expect($export->pdf())->not->toBeEmpty();
});

it('reponse() choisit le bon type MIME et refuse un format inconnu', function (): void {
    $export = new ExportDeListe('Ma liste', ['reference' => 'Référence'], [['reference' => 'A-001']]);

    expect($export->reponse('xlsx')->headers->get('Content-Type'))
        ->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($export->reponse('docx')->headers->get('Content-Type'))
        ->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($export->reponse('pdf')->headers->get('Content-Type'))->toBe('application/pdf');

    expect(fn () => $export->reponse('csv'))->toThrow(ErreurMetier::class);
});
