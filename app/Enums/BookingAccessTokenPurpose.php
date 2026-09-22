<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingAccessTokenPurpose: string
{
    case Complete = 'COMPLETE';
    case Questionnaire = 'QUESTIONNAIRE';
    case Survey = 'SURVEY';
}
