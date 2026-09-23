<?php

namespace Database\Seeders;

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PhotosDeLogement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Models\Diapositive;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Referentiels\Models\Quartier;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Repas\Models\Livreur;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Avis;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Models\Chauffeur;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\ImageManager;

/**
 * Contenu de démonstration pour la page d'accueil : résidences, logements, PHOTOS RÉELLEMENT
 * GÉNÉRÉES (via le même pipeline que les dépôts propriétaires), quelques avis vérifiés publiés
 * pour que « les mieux notées » ait de quoi se classer. Jamais en production (voir DatabaseSeeder).
 */
class DemoContenuSeeder extends Seeder
{
    /** @var list<array{commune: string, quartier: string, residence: string, type: string, nom: string, prix: int, couleur: string}> */
    private const RESIDENCES = [
        ['commune' => 'Cocody', 'quartier' => 'Angré', 'residence' => 'Résidence Les Palmiers', 'type' => 'f3', 'nom' => 'Appartement Jasmin', 'prix' => 35000, 'couleur' => '#1E3A5F'],
        ['commune' => 'Cocody', 'quartier' => 'II Plateaux', 'residence' => 'Résidence Riviera Golf', 'type' => 'villa', 'nom' => 'Villa Bougainville', 'prix' => 95000, 'couleur' => '#2E5A87'],
        ['commune' => 'Marcory', 'quartier' => 'Zone 4', 'residence' => 'Résidence Zone 4 Business', 'type' => 'studio', 'nom' => 'Studio Kora', 'prix' => 22000, 'couleur' => '#3E7CB1'],
        ['commune' => 'Plateau', 'quartier' => 'Plateau Centre', 'residence' => 'Résidence Le Plateau Affaires', 'type' => 'f2', 'nom' => 'Appartement Baobab', 'prix' => 40000, 'couleur' => '#1E3A5F'],
        ['commune' => 'Marcory', 'quartier' => 'Biétry', 'residence' => 'Résidence Biétry Lagune', 'type' => 'f4', 'nom' => 'Appartement Lagune Bleue', 'prix' => 55000, 'couleur' => '#2E5A87'],
        ['commune' => 'Cocody', 'quartier' => 'Angré 8e Tranche', 'residence' => 'Résidence Angré Prestige', 'type' => 'duplex', 'nom' => 'Duplex Étoile', 'prix' => 75000, 'couleur' => '#3E7CB1'],
    ];

    /** @var list<array{titre: string, legende: string, couleur: string}> */
    private const SLIDES = [
        ['titre' => 'Résidences meublées haut de gamme', 'legende' => 'Un accueil, un ménage et des services sur place, à Abidjan', 'couleur' => '#152B47'],
        ['titre' => 'Le confort d’un hôtel, l’espace d’un chez-soi', 'legende' => 'Studios, appartements et villas entièrement équipés', 'couleur' => '#1E3A5F'],
        ['titre' => 'Séjournez au cœur de Cocody, Marcory et Plateau', 'legende' => 'Des résidences sélectionnées dans les meilleurs quartiers', 'couleur' => '#2E5A87'],
        ['titre' => 'Réservation simple, séjour sans souci', 'legende' => 'Paiement sécurisé, code d’arrivée et assistance dédiée', 'couleur' => '#3E7CB1'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Le contenu de démonstration ne se lance pas en production.');

            return;
        }

        $this->call(ReferentielsSeeder::class);
        $this->seederFichesPartenaires();

        $residencesAtraiter = collect(self::RESIDENCES)->filter(fn (array $d) => ! Residence::query()->where('nom', $d['residence'])->exists());
        if ($residencesAtraiter->isEmpty()) {
            $this->seederCarrousel(User::query()->where('profil', Profil::Administrateur)->firstOrFail());

            return; // tout est déjà seedé : on ne recrée jamais de propriétaire, client ou administrateur en trop
        }

        $administrateur = User::query()->where('profil', Profil::Administrateur)->first()
            ?? User::factory()->profil(Profil::Administrateur)->create();
        $proprietaire = Proprietaire::query()->first() ?? Proprietaire::factory()->create();
        $clients = User::factory()->count(4)->create();

        $photos = app(PhotosDeLogement::class);

        foreach (self::RESIDENCES as $indice => $donnee) {
            // Déjà seedée (nom unique dans cette liste de démonstration) : on ne la reproduit jamais en double.
            if (Residence::query()->where('nom', $donnee['residence'])->exists()) {
                continue;
            }

            $quartier = Quartier::query()->whereHas('commune', fn ($q) => $q->where('nom', $donnee['commune']))
                ->where('nom', $donnee['quartier'])->first();
            if ($quartier === null) {
                continue; // le référentiel ne connaît pas ce quartier sur cet environnement : on saute plutôt que d'échouer
            }

            $residence = Residence::create([
                'proprietaire_id' => $proprietaire->id,
                'quartier_id' => $quartier->id,
                'nom' => $donnee['residence'],
                'description' => 'Résidence meublée au cœur de '.$donnee['commune'].', proche des commerces et des axes principaux.',
                // Une résidence sur deux mise en avant : de quoi peupler la section d'accueil sans tout remplir.
                'mise_en_avant' => $indice % 2 === 0,
            ]);

            $type = TypeLogement::firstWhere('code', $donnee['type']) ?? TypeLogement::firstOrCreate(['code' => 'f3'], ['nom' => 'Appartement 3 pièces', 'nombre_pieces' => 3]);

            $logement = Logement::create([
                'residence_id' => $residence->id,
                'type_logement_id' => $type->id,
                'nom' => $donnee['nom'],
                'nombre_pieces' => $type->nombre_pieces ?? 3,
                'nombre_chambres' => max(1, ((int) ($type->nombre_pieces ?? 3)) - 1),
                'nombre_lits' => 2,
                'nombre_salles_de_bain' => 1,
                'capacite_de_base' => 2,
                'capacite_maximale' => 4,
                'surface_m2' => 60,
                'caution' => $donnee['prix'],
                'prix_proprietaire' => (int) round($donnee['prix'] * 0.7),
                'prix_vente' => $donnee['prix'],
                'etat_publication' => EtatPublication::Publie,
                'publie_le' => now(),
                'mise_en_avant' => $indice < 3,
            ]);

            foreach (range(1, 3) as $n) {
                $photos->ajouter($logement, $this->imageGeneree($donnee['couleur']), null, $administrateur);
            }

            // Deux résidences sur trois reçoivent un séjour clôturé + avis publié : de quoi classer les « mieux notées ».
            if ($indice % 3 !== 2) {
                $client = $clients[$indice % $clients->count()];
                $sejour = Sejour::factory()->create([
                    'logement_id' => $logement->id, 'client_id' => $client->id, 'etat' => EtatDuSejour::Cloture,
                    'arrivee' => now()->subMonths(2), 'depart' => now()->subMonths(2)->addDays(4),
                ]);
                Avis::create([
                    'sejour_id' => $sejour->id,
                    'note' => [5, 4, 5, 3, 4][$indice] ?? 4,
                    'commentaire' => 'Séjour très agréable, logement conforme aux photos et bien situé.',
                    'statut' => 'publie',
                    'modere_par' => $administrateur->id,
                    'modere_le' => now()->subMonth(),
                ]);
            }
        }

        $this->seederCarrousel($administrateur);
    }

    /**
     * Relie chaque compte partenaire de démonstration (créé par DatabaseSeeder, un par Profil)
     * à sa fiche métier : sans elle, l'espace self-service correspondant répond 404 partout
     * (chaque contrôleur exige une fiche déjà créée par le back office — jamais de création
     * silencieuse, voir ResoutLeChauffeurConnecte et ses équivalents). Idempotent : sautée si
     * la fiche existe déjà, jamais recréée en double.
     */
    private function seederFichesPartenaires(): void
    {
        $lier = function (string $email, string $modele, array $etat = []): void {
            $utilisateur = User::query()->where('email', $email)->first();
            if ($utilisateur === null) {
                return; // profil pas encore seedé sur cet environnement : rien à lier
            }
            if ($modele::query()->where('user_id', $utilisateur->id)->exists()) {
                return;
            }
            $modele::factory()->create(['user_id' => $utilisateur->id, ...$etat]);
        };

        $lier('proprietaire@residences.test', Proprietaire::class);
        $lier('apporteur@residences.test', Apporteur::class);
        $lier('chauffeur@residences.test', Chauffeur::class);
        $lier('restaurateur@residences.test', Restaurateur::class, ['pourcentage_plateforme' => 30]);
        $lier('livreur@residences.test', Livreur::class);
    }

    /** Carrousel d'en-tête de la page d'accueil (CdC § 12) : 4 diapositives, jamais dupliquées si déjà seedées. */
    private function seederCarrousel(User $administrateur): void
    {
        if (Diapositive::query()->count() > 0) {
            return;
        }

        foreach (self::SLIDES as $ordre => $slide) {
            Diapositive::create([
                'image_url' => $this->imageDeDiapositive($slide['couleur']),
                'legende' => $slide['titre'],
                'ordre' => $ordre + 1,
                'actif' => true,
                'cree_par' => $administrateur->id,
            ]);
        }
    }

    /**
     * Diapositive large format (1920×800), fond seul — SANS texte ni accent dessinés dans l'image.
     * `SectionCarrousel.tsx` affiche déjà `legende` en HTML (police, dégradé de lisibilité, filet
     * doré) par-dessus cette image ; dessiner le même texte ici le dupliquait, visible en surimpression
     * après recadrage `object-fit: cover` (défaut signalé par le client : « deux sliders » sur l'accueil).
     */
    private function imageDeDiapositive(string $couleur): string
    {
        $image = (new ImageManager(Driver::class))->createImage(1920, 800)->fill($couleur);

        $chemin = 'carrousel/'.Str::uuid()->toString().'.jpg';
        Storage::disk('public')->put($chemin, (string) $image->encode(new JpegEncoder(quality: 85)));

        return Storage::disk('public')->url($chemin);
    }

    /**
     * Image « à remplacer » de démonstration (sans dépendance réseau, même moteur que les dépôts
     * propriétaires) : fond de couleur + deux anneaux décoratifs (même motif que le filet du hero),
     * SANS titre ni légende dessinés dans les pixels — la carte HTML affiche déjà ce texte par-dessus
     * (même correctif que le carrousel : un texte dupliqué dans l'image et en HTML se voyait en
     * surimpression après recadrage). Reste clairement un repère « photo à venir », pas une photo réelle.
     */
    private function imageGeneree(string $couleur): UploadedFile
    {
        $image = (new ImageManager(Driver::class))->createImage(1600, 1000)->fill($couleur);
        $image->drawCircle(function (CircleFactory $cercle): void {
            $cercle->at(900, 300)->radius(420)->border('rgba(255,255,255,0.14)', 2);
        });
        $image->drawCircle(function (CircleFactory $cercle): void {
            $cercle->at(420, 760)->radius(260)->border('rgba(255,255,255,0.10)', 2);
        });

        $chemin = sys_get_temp_dir().'/'.Str::uuid()->toString().'.jpg';
        file_put_contents($chemin, (string) $image->encode(new JpegEncoder(quality: 85)));

        return new UploadedFile($chemin, basename($chemin), 'image/jpeg', null, true);
    }
}
