<?php

declare(strict_types=1);

namespace App\Enums;

enum WaitlistSource: string
{
    case Rms = 'RMS';
    case Engine = 'ENGINE';
}
