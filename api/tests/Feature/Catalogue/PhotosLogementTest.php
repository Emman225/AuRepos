<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PhotosDeLogement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->auteur = User::factory()->profil(Profil::Gestionnaire)->create();
    $this->withToken(auth('api')->login($this->auteur));
    $this->logement = Logement::factory()->create();
    $this->auteur->residences()->attach($this->logement->residence_id);
    $this->base = "/api/v1/backoffice/residences/{$this->logement->residence_id}/logements/{$this->logement->id}/photos";
});

function deposer(string $legende = 'Salon', int $largeur = 2400, int $hauteur = 1600, string $nom = 'salon.jpg')
{
    return test()->post(test()->base, [
        'photo' => UploadedFile::fake()->image($nom, $largeur, $hauteur),
        'legende' => $legende,
    ], ['Accept' => 'application/json']);
}

it('dépose une photo et produit l’original privé, l’affichage réduit et la vignette', function (): void {
    $photo = PhotoLogement::findOrFail(deposer()->assertCreated()->json('data.id'));

    Storage::disk('local')->assertExists($photo->chemin_original);
    Storage::disk('public')->assertExists($photo->chemin_affichage);
    Storage::disk('public')->assertExists($photo->chemin_vignette);
    // L'original, non filigrané, n'est jamais sur le disque public.
    Storage::disk('public')->assertMissing($photo->chemin_original);

    [$largeurAffichage] = getimagesize(Storage::disk('public')->path($photo->chemin_affichage));
    [$largeurVignette] = getimagesize(Storage::disk('public')->path($photo->chemin_vignette));
    expect($largeurAffichage)->toBe(PhotosDeLogement::LARGEUR_AFFICHAGE)
        ->and($largeurVignette)->toBe(PhotosDeLogement::LARGEUR_VIGNETTE)
        ->and($photo->largeur)->toBe(2400)
        ->and($photo->legende)->toBe('Salon')
        ->and($photo->ajoutee_par_administration)->toBeTrue()
        ->and($photo->etat)->toBe('acceptee');
});

it('pose un filigrane sur la version d’affichage', function (): void {
    app(Parametres::class)->enregistrer('proprietaires', ['filigrane' => 'RÉSIDENCES MEUBLÉES'], User::factory()->profil(Profil::Administrateur)->create());
    $photo = PhotoLogement::findOrFail(deposer()->json('data.id'));

    // L'image de test est unie : sans filigrane, le coin bas-droit aurait partout la même couleur.
    $image = imagecreatefromjpeg(Storage::disk('public')->path($photo->chemin_affichage));
    $couleurs = [];
    for ($x = imagesx($image) - 400; $x < imagesx($image) - 10; $x += 3) {
        for ($y = imagesy($image) - 80; $y < imagesy($image) - 10; $y += 3) {
            $couleurs[imagecolorat($image, $x, $y)] = true;
        }
    }

    expect(count($couleurs))->toBeGreaterThan(5);
});

it('fait de la première photo la couverture, et n’en garde jamais qu’une', function (): void {
    $premiere = deposer('Salon')->json('data.id');
    $seconde = deposer('Chambre')->json('data.id');

    expect(PhotoLogement::find($premiere)->couverture)->toBeTrue()
        ->and(PhotoLogement::find($seconde)->couverture)->toBeFalse();

    test()->putJson("{$this->base}/{$seconde}", ['couverture' => true])->assertOk();

    expect(PhotoLogement::find($premiere)->couverture)->toBeFalse()
        ->and(PhotoLogement::find($seconde)->couverture)->toBeTrue();
});

it('fait garantir par la base qu’il n’y a qu’une couverture par logement', function (): void {
    deposer();
    $seconde = deposer('Chambre')->json('data.id');

    expect(fn () => DB::transaction(fn () => DB::table('photos_logement')->where('id', $seconde)->update(['couverture' => true])))
        ->toThrow(QueryException::class);
});

it('refuse un fichier qui n’est pas une photo exploitable', function (UploadedFile $fichier): void {
    test()->post($this->base, ['photo' => $fichier], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['photo']]);
})->with([
    'un PDF' => [fn () => UploadedFile::fake()->create('plan.pdf', 200, 'application/pdf')],
    'un GIF' => [fn () => UploadedFile::fake()->image('anim.gif', 1200, 900)],
    'une photo trop petite' => [fn () => UploadedFile::fake()->image('petite.jpg', 640, 480)],
    'une photo de plus de 5 Mo' => [fn () => UploadedFile::fake()->image('lourde.jpg', 1200, 900)->size(6 * 1024)],
]);

it('refuse une photo au-delà du maximum paramétré', function (): void {
    app(Parametres::class)->enregistrer('proprietaires', ['photos_minimum' => 1, 'photos_maximum' => 2], User::factory()->profil(Profil::Administrateur)->create());

    deposer('Une')->assertCreated();
    deposer('Deux')->assertCreated();
    deposer('Trois')->assertStatus(422)->assertJsonPath('errors.code.0', 'photos_maximum_atteint');
});

it('indique s’il y a assez de photos pour publier', function (): void {
    deposer();

    $galerie = test()->getJson($this->base)->assertOk()->json('data');

    expect($galerie['nombre'])->toBe(1)->and($galerie['minimum'])->toBe(10)
        ->and($galerie['maximum'])->toBe(30)->and($galerie['assez_pour_publier'])->toBeFalse();
});

it('change la légende et l’ordre des photos', function (): void {
    $a = deposer('A')->json('data.id');
    $b = deposer('B')->json('data.id');
    $c = deposer('C')->json('data.id');

    test()->putJson("{$this->base}/{$a}", ['legende' => 'Cuisine équipée'])->assertOk();
    $galerie = test()->putJson("{$this->base}/ordre", ['ordre' => [$c, $a, $b]])->assertOk()->json('data.photos');

    expect(array_column($galerie, 'legende'))->toBe(['C', 'Cuisine équipée', 'B']);
});

it('refuse un ordre incomplet ou qui cite la photo d’un autre logement', function (): void {
    $a = deposer('A')->json('data.id');
    deposer('B');
    $ailleurs = PhotoLogement::create([
        'logement_id' => Logement::factory()->create()->id, 'chemin_original' => 'x', 'chemin_affichage' => 'y',
        'chemin_vignette' => 'z', 'largeur' => 1, 'hauteur' => 1, 'taille_octets' => 1,
    ]);

    test()->putJson("{$this->base}/ordre", ['ordre' => [$a]])->assertStatus(422);
    test()->putJson("{$this->base}/ordre", ['ordre' => [$a, $ailleurs->id]])->assertStatus(422);
});

it('recadre à partir de l’original et change l’adresse servie', function (): void {
    $id = deposer()->json('data.id');
    $avant = PhotoLogement::find($id)->urlAffichage();

    test()->travel(5)->seconds();
    test()->postJson("{$this->base}/{$id}/recadrage", ['x' => 200, 'y' => 100, 'largeur' => 1200, 'hauteur' => 900])->assertOk();

    $photo = PhotoLogement::find($id);
    [$largeur, $hauteur] = getimagesize(Storage::disk('public')->path($photo->chemin_affichage));
    expect([$largeur, $hauteur])->toBe([1200, 900])
        ->and($photo->urlAffichage())->not->toBe($avant);
});

it('refuse un cadre qui dépasse la photo ou qui la rendrait trop petite', function (): void {
    $id = deposer()->json('data.id');

    test()->postJson("{$this->base}/{$id}/recadrage", ['x' => 2000, 'y' => 0, 'largeur' => 1200, 'hauteur' => 900])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'recadrage_hors_image');
    test()->postJson("{$this->base}/{$id}/recadrage", ['x' => 0, 'y' => 0, 'largeur' => 300, 'hauteur' => 300])->assertStatus(422);
});

it('exige un motif pour supprimer, le consigne, efface les fichiers et promeut une nouvelle couverture', function (): void {
    $couverture = PhotoLogement::find(deposer('Salon')->json('data.id'));
    $suivante = deposer('Chambre')->json('data.id');

    test()->deleteJson("{$this->base}/{$couverture->id}", [])->assertStatus(422);
    test()->deleteJson("{$this->base}/{$couverture->id}", ['motif' => 'Photo floue, à refaire'])->assertOk();

    expect(PhotoLogement::find($couverture->id))->toBeNull()
        ->and(PhotoLogement::find($suivante)->couverture)->toBeTrue();
    Storage::disk('local')->assertMissing($couverture->chemin_original);
    Storage::disk('public')->assertMissing($couverture->chemin_affichage);

    $trace = EntreeAudit::where('action', 'suppression_photo')->sole();
    expect($trace->recit)->toContain('Photo floue, à refaire')
        ->and($trace->user_id)->toBe($this->auteur->id);
});

it('ne trouve une photo que dans son propre logement', function (): void {
    $id = deposer()->json('data.id');
    $autre = Logement::factory()->create(['residence_id' => Residence::factory()]);

    test()->putJson("/api/v1/backoffice/residences/{$autre->residence_id}/logements/{$autre->id}/photos/{$id}", ['legende' => 'Intrus'])
        ->assertNotFound();
});

it('ferme les photos aux profils hors exploitation', function (Profil $profil): void {
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil($profil)->create()));

    test()->getJson($this->base)->assertForbidden();
})->with([Profil::Client, Profil::Proprietaire, Profil::AgentTerrain]);
