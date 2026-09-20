<?php

declare(strict_types=1);

namespace App\Enums;

enum EngineLabelTone: string
{
    case Wait = 'wait';
    case Comp = 'comp';
    case Pend = 'pend';
    case Canc = 'canc';
    case Hold = 'hold';
    case Conf = 'conf';
}
