<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Fiscalite\Services\Factures;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\AlerteStickersFneMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->administrateur = User::factory()->profil(Profil::Administrateur)->create();
    $this->gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    config(['fne.enabled' => true, 'fne.base_url' => 'http://fne.test/ws', 'fne.api_key' => 'cle-test', 'fne.point_of_sale' => 'PDV-TEST', 'fne.establishment' => 'DALAKOUN TEST']);
});

function connecteFiscalite(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

/** @return array<string, mixed> */
function devisDeTest(): array
{
    return [
        'nombre_de_nuits' => 3, 'hebergement_net_ht' => 90000, 'extras_ht' => 0, 'transfert_ht' => 0,
        'total_ht' => 90000, 'total_tva' => 16200, 'tdt' => 900, 'taxe_de_sejour' => 3000,
        'autres_taxes' => 3900, 'net_a_payer' => 110100,
    ];
}

function sejourDeTest(?User $client = null): Sejour
{
    $client ??= User::factory()->create();

    return Sejour::factory()->create(['client_id' => $client->id, 'devis' => devisDeTest(), 'net_a_payer' => 110100]);
}

// ---------------------------------------------------------------- génération

it('génère une facture depuis le devis figé du séjour, sans rien recalculer', function (): void {
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();

    $reponse = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])
        ->assertCreated();

    expect($reponse->json('data.montant_ht'))->toBe(90000)
        ->and($reponse->json('data.montant_tva'))->toBe(16200)
        ->and($reponse->json('data.autres_taxes'))->toBe(3900)
        ->and($reponse->json('data.montant_ttc'))->toBe(110100)
        ->and($reponse->json('data.statut_transmission'))->toBe('a_transmettre')
        ->and($reponse->json('data.numero'))->toStartWith('FAC-');

    $lignes = collect($reponse->json('data.lignes'));
    expect($lignes->pluck('description'))->toContain('Hébergement', 'Taxe de développement touristique (TDT)', 'Taxe de séjour');
    // TVAD (0 %) et non une liste vide : la DGI rejette toute ligne sans code de taxe (essai réel).
    expect($lignes->firstWhere('description', 'Taxe de développement touristique (TDT)')['taxes'])->toBe(['TVAD']);
});

it('refuse une deuxième facture pour le même séjour', function (): void {
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->assertCreated();

    test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])
        ->assertUnprocessable()->assertJsonPath('errors.code.0', 'facture_deja_emise');
});

it('autorise plusieurs proforma pour le même séjour', function (): void {
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'proforma'])->assertCreated();

    test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'proforma'])
        ->assertCreated()
        ->assertJsonPath('data.numero', fn ($v) => str_starts_with((string) $v, 'PRO-'));
});

// ---------------------------------------------------------------- transmission

it('transmet une facture à la DGI et enregistre la référence, le QR et le solde de stickers', function (): void {
    Http::fake([
        'fne.test/ws/external/invoices/sign' => Http::response([
            'ncc' => '1234567A', 'reference' => 'FNE-REF-0001', 'token' => 'https://fne.test/qr/abc', 'warning' => false, 'balance_sticker' => 998,
            'invoice' => ['items' => [['id' => 'item-1', 'quantity' => 3], ['id' => 'item-2', 'quantity' => 1], ['id' => 'item-3', 'quantity' => 1]]],
        ], 200),
    ]);
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');

    $reponse = test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")->assertOk();

    expect($reponse->json('data.statut_transmission'))->toBe('transmise')
        ->and($reponse->json('data.reference_dgi'))->toBe('FNE-REF-0001')
        ->and($reponse->json('data.token_qr'))->toBe('https://fne.test/qr/abc')
        ->and($reponse->json('data.solde_stickers'))->toBe(998);

    Http::assertSent(fn ($requete) => $requete->hasHeader('Authorization', 'Bearer cle-test')
        && $requete['pointOfSale'] === 'PDV-TEST'
        && $requete['establishment'] === 'DALAKOUN TEST');
});

it('enregistre le refus de la DGI avec son motif exact', function (): void {
    Http::fake(['fne.test/ws/*' => Http::response(['message' => 'Point de vente inconnu'], 400)]);
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');

    $reponse = test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")
        ->assertUnprocessable()->assertJsonPath('errors.code.0', 'fne_refusee');

    expect($reponse->json('message'))->toBe('Point de vente inconnu');
    $facture = Facture::find($facture['id']);
    expect($facture->statut_transmission->value)->toBe('refusee')->and($facture->motif_refus_dgi)->toBe('Point de vente inconnu');
});

it('refuse de transmettre quand la FNE n’est pas configurée', function (): void {
    config(['fne.enabled' => false]);
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');

    test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")
        ->assertUnprocessable()->assertJsonPath('errors.code.0', 'fne_non_configuree');

    expect(Facture::find($facture['id'])->statut_transmission->value)->toBe('non_configuree');
});

it('alerte les administrateurs quand le solde de stickers passe sous le seuil', function (): void {
    Mail::fake();
    config(['fne.seuil_alerte_stickers' => 50]);
    Http::fake(['fne.test/ws/*' => Http::response(['reference' => 'R', 'token' => 't', 'ncc' => 'n', 'balance_sticker' => 30, 'invoice' => ['items' => []]], 200)]);
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');

    test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")->assertOk();

    Mail::assertQueued(AlerteStickersFneMail::class, fn ($m) => $m->hasTo($this->administrateur->email) && $m->solde === 30);
});

it('n’alerte pas quand le solde de stickers reste au-dessus du seuil', function (): void {
    Mail::fake();
    config(['fne.seuil_alerte_stickers' => 50]);
    Http::fake(['fne.test/ws/*' => Http::response(['reference' => 'R', 'token' => 't', 'ncc' => 'n', 'balance_sticker' => 998, 'invoice' => ['items' => []]], 200)]);
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');

    test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")->assertOk();

    Mail::assertNotQueued(AlerteStickersFneMail::class);
});

it('un gestionnaire ne peut pas transmettre une facture', function (): void {
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');

    connecteFiscalite($this->gestionnaire);
    test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")->assertForbidden();
});

// ---------------------------------------------------------------- avoir

it('émet un avoir sur une facture transmise, avec un motif obligatoire', function (): void {
    // /refund s'appelle avec l'identifiant INTERNE de la facture (`invoice.id`, un UUID), jamais
    // sa référence lisible — vérifié par un essai réel contre l'environnement de test de la DGI.
    Http::fake([
        'fne.test/ws/external/invoices/sign' => Http::response(['reference' => 'FNE-REF-0002', 'token' => 't', 'ncc' => 'n', 'balance_sticker' => 997, 'invoice' => ['id' => 'uuid-facture-0002', 'items' => [['id' => 'i1', 'quantity' => 3]]]], 200),
        'fne.test/ws/external/invoices/uuid-facture-0002/refund' => Http::response(['reference' => 'FNE-AVR-0001', 'token' => 't2', 'ncc' => 'n', 'balance_sticker' => 996, 'invoice' => []], 200),
    ]);
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');
    test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")->assertOk();

    $reponse = test()->postJson("/api/v1/backoffice/factures/{$facture['id']}/avoir", ['motif' => 'Annulation à la demande du client.'])
        ->assertCreated();

    expect($reponse->json('data.type'))->toBe('avoir')
        ->and($reponse->json('data.montant_ttc'))->toBe(-110100)
        ->and($reponse->json('data.statut_transmission'))->toBe('transmise')
        ->and($reponse->json('data.numero'))->toStartWith('AVR-');

    Http::assertSent(fn ($requete) => str_ends_with((string) $requete->url(), '/external/invoices/uuid-facture-0002/refund'));
});

it('refuse un avoir sans motif', function (): void {
    Http::fake(['fne.test/ws/*' => Http::response(['reference' => 'R', 'token' => 't', 'ncc' => 'n', 'balance_sticker' => 1, 'invoice' => ['items' => []]], 200)]);
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');
    test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")->assertOk();

    test()->postJson("/api/v1/backoffice/factures/{$facture['id']}/avoir", [])->assertUnprocessable();
});

it('refuse un avoir sur une facture jamais transmise', function (): void {
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');

    test()->postJson("/api/v1/backoffice/factures/{$facture['id']}/avoir", ['motif' => 'Test.'])
        ->assertUnprocessable()->assertJsonPath('errors.code.0', 'facture_non_transmise');
});

// ---------------------------------------------------------------- accès

it('un profil hors exploitation n’accède pas aux factures', function (): void {
    connecteFiscalite(User::factory()->profil(Profil::Proprietaire)->create());

    test()->getJson('/api/v1/backoffice/factures')->assertForbidden();
});

it('un gestionnaire n’accède pas du tout à l’écran des factures', function (): void {
    connecteFiscalite($this->gestionnaire);

    test()->getJson('/api/v1/backoffice/factures')->assertForbidden();
});

// ---------------------------------------------------------------- PDF

it('télécharge le PDF d’une facture, même non transmise', function (): void {
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'proforma'])->json('data');

    test()->get("/api/v1/backoffice/factures/{$facture['id']}/pdf")
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('génère un vrai QR code (pas le texte brut du jeton) une fois la facture transmise', function (): void {
    Http::fake(['fne.test/ws/*' => Http::response(['reference' => 'R', 'token' => 'https://fne.test/qr/xyz', 'ncc' => 'n', 'balance_sticker' => 998, 'invoice' => ['items' => []]], 200)]);
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->json('data');
    test()->putJson("/api/v1/backoffice/factures/{$facture['id']}/transmission")->assertOk();

    // Le PDF compilé (DomPDF) est compressé, donc illisible en texte : on vérifie ce qui est
    // réellement transmis au gabarit plutôt que de fouiller le binaire du PDF.
    $donnees = app(Factures::class)->donneesPdf(Facture::findOrFail($facture['id']));

    expect($donnees['qrCodeSvg'])->not->toBeNull();
    $svg = base64_decode((string) $donnees['qrCodeSvg']);
    expect($svg)->toContain('<svg')->not->toContain('https://fne.test/qr/xyz');
});

// ---------------------------------------------------------------- liste noire (rappel du guichet)

it('transmet le RCCM du client et le bon de commande au gabarit d’un client professionnel', function (): void {
    connecteFiscalite($this->administrateur);
    $utilisateur = User::factory()->create();
    Client::de($utilisateur)->update(['nature' => 'b2b', 'raison_sociale' => 'ACME SARL', 'ncc' => '1234567A', 'rccm' => 'CI-ABJ-2024-B-00001']);
    $sejour = Sejour::factory()->create(['client_id' => $utilisateur->id, 'devis' => devisDeTest(), 'net_a_payer' => 110100, 'bon_de_commande' => 'BC-2026-042']);
    $facture = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'proforma'])->json('data');

    $donnees = app(Factures::class)->donneesPdf(Facture::findOrFail($facture['id']));

    expect($donnees['client']['rccm'])->toBe('CI-ABJ-2024-B-00001')
        ->and($donnees['client']['bon_de_commande'])->toBe('BC-2026-042');
});

it('porte le code d’exonération sur la ligne, jamais une absence de taxe', function (): void {
    connecteFiscalite($this->administrateur);
    $client = User::factory()->create();
    Client::de($client)->update(['tva_hebergement' => false, 'code_exoneration' => 'TVAD']);
    $sejour = sejourDeTest($client);

    $reponse = test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'facture'])->assertCreated();

    $hebergement = collect($reponse->json('data.lignes'))->firstWhere('description', 'Hébergement');
    expect($hebergement['taxes'])->toBe(['TVAD']);
});

// ---------------------------------------------------------------- export (CdC § 6.8)

it('exporte la liste des factures en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    connecteFiscalite($this->administrateur);
    $sejour = sejourDeTest();
    test()->postJson('/api/v1/backoffice/factures', ['sejour_id' => $sejour->id, 'type' => 'proforma'])->assertCreated();

    $reponse = test()->getJson('/api/v1/backoffice/factures/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /factures/export avant /factures/{facture} : "export" n’est jamais pris pour un identifiant', function (): void {
    connecteFiscalite($this->administrateur);

    test()->getJson('/api/v1/backoffice/factures/export?format=xlsx')->assertOk();
});

it('ferme l’export des factures aux gestionnaires', function (): void {
    connecteFiscalite($this->gestionnaire);

    test()->getJson('/api/v1/backoffice/factures/export?format=xlsx')->assertForbidden();
});
