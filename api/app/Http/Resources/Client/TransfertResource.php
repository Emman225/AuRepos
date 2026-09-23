<?php

namespace App\Http\Resources\Client;

use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Transferts\Models\Transfert;
use App\Domain\Transferts\Services\GestionDesTransferts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mon espace › Mes extras et transferts. Le code de prise en charge en clair n'apparaît QUE
 * dans la fiche détail du titulaire du séjour, jamais dans une liste (même règle que le code
 * d'arrivée : CdC § 11).
 *
 * @mixin Transfert
 */
class TransfertResource extends JsonResource
{
    public function __construct(Transfert $resource, private readonly bool $avecCode = false)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'lieu_de_prise_en_charge' => $this->lieu_de_prise_en_charge,
            'commune' => $this->whenLoaded('commune', fn () => $this->commune->nom),
            'type_vehicule_souhaite' => $this->whenLoaded('typeVehiculeSouhaite', fn () => $this->typeVehiculeSouhaite->nom),
            'date_heure_prevue' => $this->date_heure_prevue->format('d/m/Y H:i'),
            'nombre_passagers' => $this->nombre_passagers,
            'nombre_bagages' => $this->nombre_bagages,
            'montant' => $this->montant,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'code_prise_en_charge' => $this->when(
                $this->avecCode,
                fn () => app(CodesSecrets::class)->lirePourLeClient($this->resource, GestionDesTransferts::CODE_PRISE_EN_CHARGE),
            ),
        ];
    }
}
