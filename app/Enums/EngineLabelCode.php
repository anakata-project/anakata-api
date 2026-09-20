<?php

declare(strict_types=1);

namespace App\Enums;

enum EngineLabelCode: string
{
    case NotShown = 'NOT_SHOWN';
    case Chartered = 'CHARTERED';
    case Closed = 'CLOSED';
    case Charter = 'CHARTER';
    case Limited = 'LIMITED';
    case Full = 'FULL';
    case OnlyNLeft = 'ONLY_N_LEFT';
    case Available = 'AVAILABLE';
}
