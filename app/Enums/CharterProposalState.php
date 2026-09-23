<?php

declare(strict_types=1);

namespace App\Enums;

enum CharterProposalState: string
{
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
}
