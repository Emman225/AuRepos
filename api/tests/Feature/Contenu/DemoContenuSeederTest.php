<?php

use App\Domain\Catalogue\Models\Residence;
use App\Domain\Contenu\Models\Diapositive;
use Database\Seeders\DemoContenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('se relance sans créer de doublon de résidences ni de diapositives', function (): void {
    $this->seed(DemoContenuSeeder::class);
    $residences = Residence::count();
    $diapositives = Diapositive::count();

    expect($diapositives)->toBe(4)->and($residences)->toBeGreaterThan(0);

    $this->seed(DemoContenuSeeder::class);

    expect(Residence::count())->toBe($residences)
        ->and(Diapositive::count())->toBe($diapositives);
});
