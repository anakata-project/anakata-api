<?php

declare(strict_types=1);

namespace App\Enums;

enum SubjectRequestChannel: string
{
    case Email = 'EMAIL';
    case Phone = 'PHONE';
    case Letter = 'LETTER';
    case InPerson = 'IN_PERSON';
}
