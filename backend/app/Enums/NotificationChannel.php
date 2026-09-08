<?php

namespace App\Enums;

/**
 * Canaux de notification supportés (§7.7).
 */
enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Sms = 'sms';
    case Email = 'email';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'Application',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
