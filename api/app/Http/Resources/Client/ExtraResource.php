<?php

namespace App\Http\Resources\Client;

use App\Domain\Extras\Models\Extra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Le catalogue des extras, tel que le client le parcourt. @mixin Extra */
class ExtraResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'prix' => $this->prix,
        ];
    }
}
