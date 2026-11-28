<?php

namespace App\Enums;

/** Who registered the organisation (D-13). */
enum RegistrationAuthority: string
{
    case Jkm = 'jkm';
    case Main = 'main';
}
