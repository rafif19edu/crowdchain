<?php

namespace App\Enums;

/** D-15 */
enum InstitutionCategory: string
{
    case ChildrensHome = 'childrens_home';
    case ElderlyCare = 'elderly_care';
    case Oku = 'oku';
    case Tahfiz = 'tahfiz';
    case OtherCareCentre = 'other_care_centre';
}
