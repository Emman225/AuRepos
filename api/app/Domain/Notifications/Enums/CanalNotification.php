<?php

namespace App\Domain\Notifications\Enums;

enum CanalNotification: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';

    public function libelle(): string
    {
        return match ($this) {
            self::Email => 'Courriel',
            self::Sms => 'SMS',
            self::Whatsapp => 'WhatsApp',
        };
    }
}
