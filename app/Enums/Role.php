<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Donor = 'donor';
    case Fundraiser = 'fundraiser';

    /** Roles a visitor may choose at registration (D-12). */
    public static function registrable(): array
    {
        return [self::Donor->value, self::Fundraiser->value];
    }
}
