<?php

namespace App\Domain\Comptes\Services;

use App\Domain\Comptes\Enums\UsageDuCode;
use App\Domain\Comptes\Models\CodeVerification;
use App\Domain\Comptes\Models\User;
use App\Mail\CodeDeVerificationMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Codes à six chiffres, à usage unique, valables quinze minutes.
 *
 * Même principe que les futurs codes d'arrivée et de livraison : le code
 * part chez son destinataire et n'existe en base que sous forme d'empreinte.
 */
final class CodesDeVerification
{
    public const DUREE_EN_MINUTES = 15;

    public const ESSAIS_MAXIMUM = 5;

    private const REFUS = 'Ce code est incorrect ou a expiré. Demandez-en un nouveau.';

    public function envoyer(User $utilisateur, UsageDuCode $usage): void
    {
        // Un nouveau code annule les précédents du même usage.
        CodeVerification::query()
            ->where('user_id', $utilisateur->id)->where('usage', $usage)->whereNull('utilise_le')
            ->delete();

        $code = (string) random_int(100000, 999999);

        CodeVerification::create([
            'user_id' => $utilisateur->id,
            'usage' => $usage,
            'code_hash' => Hash::make($code),
            'expire_le' => now()->addMinutes(self::DUREE_EN_MINUTES),
        ]);

        // Un envoi manqué ne bloque jamais l'opération (CdC § 13.2) : le client
        // pourra demander un nouveau code. L'échec part au journal.
        try {
            Mail::to($utilisateur->email)->send(new CodeDeVerificationMail($utilisateur, $usage, $code));
        } catch (Throwable $e) {
            Log::error('Envoi du code de vérification impossible', [
                'user_id' => $utilisateur->id, 'usage' => $usage->value, 'erreur' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Consomme le code : il ne resservira pas.
     *
     * @throws ValidationException si le code est faux, expiré, déjà utilisé ou trop essayé
     */
    public function consommer(User $utilisateur, UsageDuCode $usage, string $code): void
    {
        $enregistrement = CodeVerification::query()
            ->where('user_id', $utilisateur->id)->where('usage', $usage)->whereNull('utilise_le')
            ->latest('id')->first();

        if ($enregistrement === null
            || $enregistrement->expire_le->isPast()
            || $enregistrement->tentatives >= self::ESSAIS_MAXIMUM) {
            throw ValidationException::withMessages(['code' => self::REFUS]);
        }

        if (! Hash::check($code, $enregistrement->code_hash)) {
            // Six chiffres se devinent en un million d'essais : on en autorise cinq.
            $enregistrement->increment('tentatives');
            throw ValidationException::withMessages(['code' => self::REFUS]);
        }

        $enregistrement->update(['utilise_le' => now()]);
    }
}
