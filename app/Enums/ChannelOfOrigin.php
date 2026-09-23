<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelOfOrigin: string
{
    case HotelWebsiteInquiry = 'Hotel Website Inquiry';
    case HotelBookingEngine = 'Hotel Booking Engine';
    case Phone = 'Phone';
    case Email = 'Email';
    case WhatsApp = 'WhatsApp';

    case HotelSocial = 'Hotel Social';
    case OrganicSearch = 'Organic Search';
    case PaidSearch = 'Paid Search';
    case PaidAds = 'Paid Ads';
    case AiLlm = 'AI / LLM';
    case EmailMarketing = 'Email Marketing';
    case Referral = 'Referral';

    case TravelAdvisor = 'Travel Advisor';
    case LuxuryAgency = 'Luxury Agency';
    case HostAgency = 'Host Agency';
    case Consortia = 'Consortia';
    case TourOperator = 'Tour Operator';
    case LuxuryTourOperator = 'Luxury Tour Operator';
    case Dmc = 'DMC';
    case IncomingOperator = 'Incoming Operator';
    case Wholesaler = 'Wholesaler';
    case CorporateDirect = 'Corporate Direct';
    case CorporateTravelAgency = 'Corporate Travel Agency';
    case BusinessTravel = 'Business Travel';
    case Mice = 'MICE';
    case Group = 'Group';

    case Gds = 'GDS';
    case Crs = 'CRS';
    case Switch = 'Switch';
    case HotelPartner = 'Hotel Partner';
    case Airline = 'Airline';
    case CreditCard = 'Credit Card';
    case MembershipClub = 'Membership Club';
    case Affiliate = 'Affiliate';
    case Influencer = 'Influencer';
    case BrandPartnership = 'Brand Partnership';
    case Complimentary = 'Complimentary';
    case Owner = 'Owner';
    case Staff = 'Staff';
    case Unknown = 'Unknown';

    public function label(): string
    {
        return $this->value;
    }

    public function group(): ChannelOfOriginGroup
    {
        return match ($this) {
            self::HotelWebsiteInquiry,
            self::HotelBookingEngine,
            self::Phone,
            self::Email,
            self::WhatsApp => ChannelOfOriginGroup::Direct,

            self::HotelSocial,
            self::OrganicSearch,
            self::PaidSearch,
            self::PaidAds,
            self::AiLlm,
            self::EmailMarketing,
            self::Referral => ChannelOfOriginGroup::Marketing,

            self::TravelAdvisor,
            self::LuxuryAgency,
            self::HostAgency,
            self::Consortia,
            self::TourOperator,
            self::LuxuryTourOperator,
            self::Dmc,
            self::IncomingOperator,
            self::Wholesaler,
            self::CorporateDirect,
            self::CorporateTravelAgency,
            self::BusinessTravel,
            self::Mice,
            self::Group => ChannelOfOriginGroup::TradeCorporateGroups,

            self::Gds,
            self::Crs,
            self::Switch,
            self::HotelPartner,
            self::Airline,
            self::CreditCard,
            self::MembershipClub,
            self::Affiliate,
            self::Influencer,
            self::BrandPartnership,
            self::Complimentary,
            self::Owner,
            self::Staff,
            self::Unknown => ChannelOfOriginGroup::DistributionPartnersOther,
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesInGroup(ChannelOfOriginGroup $group): array
    {
        $values = [];

        foreach (self::cases() as $case) {
            if ($case->group() === $group) {
                $values[] = $case->value;
            }
        }

        return $values;
    }
}
