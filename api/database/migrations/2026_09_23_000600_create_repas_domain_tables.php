<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Repas et boissons (CdC — « Repas et boissons ») : « Une commande de nourriture et de
| boissons livrée au logement, préparée par un restaurateur partenaire… suit le circuit
| vente-livraison de Mon Gravier (bon de préparation, livreur, code de livraison). »
|
| Le code de livraison réutilise codes_secrets (usage 'livraison', déjà prévu par sa
| contrainte CHECK) : rien à créer ici pour le chiffrement.
|
| Rémunération du livreur : comme pour le chauffeur des transferts, le CdC ne donne
| AUCUNE formule automatique (« grille propre ou forfait » est ambigu) — le montant versé
| est donc saisi MANUELLEMENT par le gestionnaire au moment de l'affectation.
|
| Bon de préparation : quantite_commandee et quantite_servie sont deux colonnes DISTINCTES
| sur la ligne de commande — la seconde n'est renseignée qu'à la préparation par le
| restaurateur, et peut différer de la première (repris de l'audit du circuit vente→
| livraison de Mon Gravier, D:\GRAVIERSNEWVERSION\apigravier).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurateurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->boolean('assujetti_tva')->default(false);
            // Nullable : comme le prix de vente d'un logement, il n'existe qu'après une
            // première double validation (App\Domain\Validation\Services\DoubleValidation).
            $table->decimal('pourcentage_plateforme', 6, 2)->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE restaurateurs ADD CONSTRAINT restaurateurs_pourcentage_valide CHECK (pourcentage_plateforme IS NULL OR (pourcentage_plateforme >= 0 AND pourcentage_plateforme <= 500))');

        Schema::create('livreurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('produits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurateur_id')->constrained('restaurateurs')->restrictOnDelete();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->string('categorie', 10)->default('plat');
            // Prix d'achat (prix restaurateur) ; le prix de vente se calcule, il n'est jamais stocké.
            $table->unsignedBigInteger('prix_restaurateur');
            $table->boolean('disponible')->default(true);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE produits ADD CONSTRAINT produits_categorie_connue CHECK (categorie IN ('plat','boisson'))");

        // Barème de livraison repas : forfait par résidence (CdC — « Forfait par résidence… »).
        // Un seul forfait par résidence : la variante « par zone » du CdC n'est pas construite
        // ici, pour éviter une règle de résolution ambiguë entre les deux (voir le rapport).
        Schema::create('baremes_livraison_repas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('residence_id')->unique()->constrained('residences')->restrictOnDelete();
            $table->unsignedBigInteger('forfait');
            $table->timestamps();
        });

        Schema::create('commandes_repas', function (Blueprint $table): void {
            $table->id();
            // 40, pas 20 : le temps de recevoir son id, la référence tient temporairement
            // « TMP-<uuid> » (même patron que App\Domain\Sejours\Models\Sejour).
            $table->string('reference', 40)->unique();
            // Jamais nullable : « pendant le séjour », jamais à la réservation.
            $table->foreignId('sejour_id')->constrained('sejours')->restrictOnDelete();
            $table->foreignId('restaurateur_id')->constrained('restaurateurs')->restrictOnDelete();
            $table->string('etat', 20)->default('demande')->index();
            $table->string('mode_reglement', 20);
            // Figé à la commande : somme des lignes au moment T.
            $table->unsignedBigInteger('montant_total');
            $table->foreignId('livreur_id')->nullable()->constrained('livreurs')->nullOnDelete();
            // Négocié et saisi à la volée par le gestionnaire, jamais un pourcentage deviné.
            $table->unsignedBigInteger('remuneration_livreur')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE commandes_repas ADD CONSTRAINT commandes_repas_etat_connu CHECK (etat IN ('demande','confirmee','en_preparation','prete','en_livraison','livree','annulee','refusee'))");
        DB::statement("ALTER TABLE commandes_repas ADD CONSTRAINT commandes_repas_mode_reglement_connu CHECK (mode_reglement IN ('en_ligne','note_du_sejour','a_terme'))");

        Schema::create('lignes_de_commande_repas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes_repas')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            // Figés au moment de la commande : un produit renommé ou re-tarifé ensuite ne
            // change jamais une commande déjà passée.
            $table->string('nom_produit', 150);
            $table->unsignedBigInteger('prix_unitaire_vente');
            $table->unsignedSmallInteger('quantite_commandee');
            // Le bon de préparation : renseigné seulement quand le restaurateur marque « Prête ».
            $table->unsignedSmallInteger('quantite_servie')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_de_commande_repas');
        Schema::dropIfExists('commandes_repas');
        Schema::dropIfExists('baremes_livraison_repas');
        Schema::dropIfExists('produits');
        Schema::dropIfExists('livreurs');
        Schema::dropIfExists('restaurateurs');
    }
};
