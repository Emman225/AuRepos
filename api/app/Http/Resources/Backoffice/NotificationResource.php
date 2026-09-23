<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Notifications\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notification */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'canal' => $this->canal->value,
            'canal_libelle' => $this->canal->libelle(),
            'modele' => $this->modele->value,
            'modele_libelle' => $this->modele->libelle(),
            'destinataire' => $this->destinataire,
            'sujet' => $this->sujet,
            'corps' => $this->corps,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'tentatives' => $this->tentatives,
            'erreur' => $this->erreur,
            'envoyee_le' => $this->envoyee_le?->format('d/m/Y H:i:s'),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
