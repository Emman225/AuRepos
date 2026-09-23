<?php

namespace App\Domain\Repas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de commande : un produit, figé au moment T (nom et prix de vente ne bougent
 * plus, même si le produit change ensuite).
 *
 * `quantite_commandee` vs `quantite_servie` : le bon de préparation (CdC, circuit
 * vente→livraison de Mon Gravier). La seconde reste nulle tant que le restaurateur n'a
 * pas marqué la commande « Prête » ; elle peut alors différer de la première (écart
 * validé par le restaurateur).
 *
 * @property int $id
 * @property int $commande_id
 * @property int $produit_id
 * @property string $nom_produit
 * @property int $prix_unitaire_vente
 * @property int $quantite_commandee
 * @property int|null $quantite_servie
 * @property-read Commande $commande
 * @property-read Produit $produit
 */
class LigneDeCommande extends Model
{
    protected $table = 'lignes_de_commande_repas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'prix_unitaire_vente' => 'integer',
            'quantite_commandee' => 'integer',
            'quantite_servie' => 'integer',
        ];
    }

    /** @return BelongsTo<Commande, $this> */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    /** @return BelongsTo<Produit, $this> */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function montant(): int
    {
        return $this->prix_unitaire_vente * $this->quantite_commandee;
    }
}
