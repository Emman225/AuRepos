<?php

namespace App\Domain\Tarification\Services;

use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Models\User;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Domain\Tarification\Models\CodePromo;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Carbon;

/** Codes promo : réduction en pourcentage ou en montant, période de validité, activable par résidence (CdC § 7.3). */
final class CodesPromo
{
    /**
     * Le code, s'il est utilisable MAINTENANT sur CETTE résidence — sinon un refus en clair.
     * Jamais silencieux : un client qui saisit un code veut savoir pourquoi il est refusé.
     */
    public function verifier(string $code, Residence $residence, ?Carbon $le = null): CodePromo
    {
        $codePromo = CodePromo::query()->whereRaw('upper(code) = ?', [mb_strtoupper(trim($code))])->first();
        $le ??= now();

        if ($codePromo === null) {
            throw new ErreurMetier('Ce code promo est inconnu.', 'code_promo_inconnu', 422);
        }
        if (! $codePromo->actif) {
            throw new ErreurMetier('Ce code promo n’est plus actif.', 'code_promo_inactif', 422);
        }
        if (! $codePromo->valableLe($le)) {
            throw new ErreurMetier(
                'Ce code promo n’est valable que du '.$codePromo->date_debut->format('d/m/Y').' au '.$codePromo->date_fin->format('d/m/Y').'.',
                'code_promo_hors_periode',
                422,
            );
        }
        if (! $codePromo->valablePour($residence)) {
            throw new ErreurMetier('Ce code promo n’est pas valable pour cette résidence.', 'code_promo_residence_invalide', 422);
        }

        return $codePromo;
    }

    /** Montant HT de la réduction sur une base donnée — les mêmes centiemes entiers que le reste du moteur. */
    public function calculerLaReduction(CodePromo $codePromo, int $baseHt): int
    {
        return $codePromo->type === 'pourcentage'
            ? CalculDuSejour::pourcentage($baseHt, (float) $codePromo->valeur)
            : min($codePromo->valeur, $baseHt);
    }

    public function creer(string $code, string $type, int $valeur, Carbon $debut, Carbon $fin, ?int $residenceId, ?string $description, User $auteur): CodePromo
    {
        return CodePromo::create([
            'code' => mb_strtoupper(trim($code)), 'type' => $type, 'valeur' => $valeur,
            'date_debut' => $debut->toDateString(), 'date_fin' => $fin->toDateString(),
            'residence_id' => $residenceId, 'description' => $description, 'cree_par' => $auteur->id,
        ]);
    }

    /** @param  array<string, mixed>  $saisie */
    public function modifier(CodePromo $codePromo, array $saisie): CodePromo
    {
        $codePromo->update($saisie);

        return $codePromo->refresh();
    }
}
