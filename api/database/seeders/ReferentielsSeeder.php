<?php

namespace Database\Seeders;

use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Equipement;
use App\Domain\Referentiels\Models\Quartier;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Referentiels\Models\Ville;
use Illuminate\Database\Seeder;

/**
 * Données de référence de départ. Contrairement au jeu de démonstration, ce
 * seeder se lance AUSSI en recette et en production, et autant de fois qu'on
 * veut : il ajoute ce qui manque, ne touche à rien de ce qui existe.
 *
 *   php artisan db:seed --class=ReferentielsSeeder --force
 *
 * La liste des quartiers est un point de départ : elle se complète depuis le back office.
 */
class ReferentielsSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const ABIDJAN = [
        'Abobo' => ['Abobo Baoulé', 'Avocatier', 'Dokui', 'PK 18', 'Sagbé'],
        'Adjamé' => ['220 Logements', 'Bracodi', 'Liberté', 'Williamsville'],
        'Anyama' => ['Anyama Centre', 'Ébimpé'],
        'Attécoubé' => ['Agban', 'Locodjro', 'Sébroko'],
        'Bingerville' => ['Bingerville Centre', 'Cité Féh Kessé', 'Akandjé'],
        'Cocody' => [
            'II Plateaux', 'II Plateaux Vallon', 'Ambassades', 'Angré', 'Angré 7e Tranche', 'Angré 8e Tranche',
            'Blockhaus', 'Cocody Centre', 'Danga', 'Faya', 'Akouédo', 'M’Badon', 'M’Pouto',
            'Riviera 2', 'Riviera 3', 'Riviera 4', 'Riviera Bonoumin', 'Riviera Golf', 'Riviera Palmeraie', 'Saint-Jean',
        ],
        'Koumassi' => ['Koumassi Centre', 'Remblais', 'Sicogi', 'Zone industrielle'],
        'Marcory' => ['Anoumabo', 'Biétry', 'Marcory Résidentiel', 'Zone 4'],
        'Plateau' => ['Plateau Centre', 'Indénié', 'Plateau Dokui'],
        'Port-Bouët' => ['Aéroport', 'Gonzagueville', 'Vridi', 'Jean Folly'],
        'Songon' => ['Songon Agban', 'Songon Kassemblé'],
        'Treichville' => ['Arras', 'Avenue 16', 'Zone 3', 'Zone portuaire'],
        'Yopougon' => ['Andokoi', 'Maroc', 'Niangon', 'Selmer', 'Sideci', 'Toits Rouges'],
    ];

    /** @var list<array{string, string, int|null}> code, nom, nombre de pièces */
    private const TYPES = [
        ['chambre', 'Chambre', 1],
        ['studio', 'Studio', 1],
        ['f2', 'Appartement 2 pièces', 2],
        ['f3', 'Appartement 3 pièces', 3],
        ['f4', 'Appartement 4 pièces', 4],
        ['f5', 'Appartement 5 pièces et plus', 5],
        ['duplex', 'Duplex', null],
        ['villa', 'Villa', null],
    ];

    /** @var list<array{string, string, string, bool}> nom, portée, icône, filtre de recherche */
    private const EQUIPEMENTS = [
        ['Wifi', 'logement', 'wifi', true],
        ['Climatisation', 'logement', 'snowflake', true],
        ['Piscine', 'residence', 'pool', true],
        ['Parking', 'residence', 'car', true],
        ['Groupe électrogène', 'residence', 'bolt', true],
        ['Gardiennage', 'residence', 'shield', true],
        ['Télévision', 'logement', 'tv', false],
        ['Cuisine équipée', 'logement', 'kitchen', false],
        ['Réfrigérateur', 'logement', 'fridge', false],
        ['Lave-linge', 'logement', 'washer', false],
        ['Eau chaude', 'logement', 'shower', false],
        ['Balcon ou terrasse', 'logement', 'balcony', false],
        ['Ascenseur', 'residence', 'elevator', false],
        ['Salle de sport', 'residence', 'dumbbell', false],
        ['Jardin', 'residence', 'tree', false],
    ];

    public function run(): void
    {
        $region = Region::firstOrCreate(['nom' => 'District autonome d’Abidjan']);
        $ville = Ville::firstOrCreate(['region_id' => $region->id, 'nom' => 'Abidjan']);

        foreach (self::ABIDJAN as $nomCommune => $quartiers) {
            $commune = Commune::firstOrCreate(['ville_id' => $ville->id, 'nom' => $nomCommune]);
            foreach ($quartiers as $nomQuartier) {
                Quartier::firstOrCreate(['commune_id' => $commune->id, 'nom' => $nomQuartier]);
            }
        }

        foreach (self::TYPES as $ordre => [$code, $nom, $pieces]) {
            TypeLogement::firstOrCreate(['code' => $code], ['nom' => $nom, 'nombre_pieces' => $pieces, 'ordre' => ($ordre + 1) * 10]);
        }

        foreach (self::EQUIPEMENTS as $ordre => [$nom, $portee, $icone, $filtre]) {
            Equipement::firstOrCreate(['nom' => $nom], [
                'portee' => $portee, 'icone' => $icone, 'filtre_recherche' => $filtre, 'ordre' => ($ordre + 1) * 10,
            ]);
        }
    }
}
