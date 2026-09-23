<?php

namespace App\Http\Resources;

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Comptes\Enums\Profil;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EntreeAudit */
class EntreeAuditResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Format des listes du cahier des charges : jj/mm/aaaa hh:mm:ss
            'date' => $this->cree_le->format('d/m/Y H:i:s'),
            'date_iso' => $this->cree_le->toIso8601String(),
            'auteur' => $this->auteur,
            'profil' => $this->profil ? Profil::tryFrom($this->profil)?->libelle() : null,
            'action' => $this->action,
            'sujet' => $this->sujet_libelle,
            'sujet_type' => $this->sujet_type,
            'sujet_id' => $this->sujet_id,
            'recit' => $this->recit,
            'avant' => $this->avant,
            'apres' => $this->apres,
            'ip' => $this->ip,
        ];
    }
}
