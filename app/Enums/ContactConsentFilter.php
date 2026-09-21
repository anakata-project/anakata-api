<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactConsentFilter: string
{
    case Marketing = 'marketing';
    case TransactionalOnly = 'transactional_only';
}
