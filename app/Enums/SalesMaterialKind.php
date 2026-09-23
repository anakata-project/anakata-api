<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesMaterialKind: string
{
    case FactSheet = 'FACT_SHEET';
    case BrandDeck = 'BRAND_DECK';
    case Photography = 'PHOTOGRAPHY';
    case ItineraryPdf = 'ITINERARY_PDF';
    case Video = 'VIDEO';
    case Other = 'OTHER';
}
