<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentKind;

final class DocumentView
{
    public static function name(DocumentKind $kind): string
    {
        return match ($kind) {
            DocumentKind::Invoice,
            DocumentKind::FinalInvoice,
            DocumentKind::Summary,
            DocumentKind::Receipt,
            DocumentKind::Voucher,
            DocumentKind::Pretrip,
            DocumentKind::WireInstructions => 'documents.proof',
        };
    }
}
