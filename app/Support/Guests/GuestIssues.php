<?php

declare(strict_types=1);

namespace App\Support\Guests;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\PngCategory;
use App\Models\Booking;
use App\Models\Guest;
use App\Services\Config\CurrentConfig;
use App\Support\Dates\Format;

final class GuestIssues
{
    public function __construct(private CurrentConfig $config) {}

    /**
     * @return list<array{severity: string, code: string, guest_id: int|null, message: string}>
     */
    public function for(Booking $booking): array
    {
        $booking->loadMissing(['departure', 'guests']);
        $settings = $this->config->engineSettings()->guests;
        $departure = $booking->departure->date;
        $returnDate = $booking->departure->returnDate();
        $issues = [];

        foreach ($booking->guests as $guest) {
            $name = $guest->displayName();
            $age = Age::at($guest->dob, $departure);

            if ($age !== null && $age < $settings->childMinAge) {
                $issues[] = $this->issue(
                    'error',
                    'under_min_age',
                    $guest->id,
                    $name.' is '.$age.' on departure — minimum age is '.$settings->childMinAge.' (OPS-004).',
                );
            }

            if ($guest->dob !== null && Age::isMinorNow($guest->dob) && $guest->guardian_consented_at === null) {
                $issues[] = $this->issue(
                    'error',
                    'guardian_consent_missing',
                    $guest->id,
                    $name.' is under 18 — guardian consent required (§6.4).',
                );
            }

            if ($guest->passport_expiry !== null && $guest->passport_expiry->toDateString() < $returnDate->toDateString()) {
                $issues[] = $this->issue(
                    'error',
                    'passport_expired',
                    $guest->id,
                    $name."'s passport expires before the return date (".Format::calendar($returnDate).').',
                );
            }

            if ($guest->first_name !== '' && ! $guest->insurance_declared && $this->isConfirmedOrLater($booking)) {
                $issues[] = $this->issue(
                    'warning',
                    'insurance_undeclared',
                    $guest->id,
                    $name.' has no travel-insurance declaration (OPS-005).',
                );
            }
        }

        if ($booking->type === BookingType::Cabin) {
            $guests = $booking->guests;
            $dated = $guests->filter(fn (Guest $guest): bool => $guest->dob !== null)->count();

            if ($guests->isNotEmpty() && $dated === $guests->count()) {
                $kids = $guests->filter(function (Guest $guest) use ($departure, $settings): bool {
                    $age = Age::at($guest->dob, $departure);

                    return $age !== null
                        && $age >= $settings->childMinAge
                        && $age <= $settings->childMaxAge;
                })->count();

                if ($kids !== $booking->children) {
                    $issues[] = $this->issue(
                        'warning',
                        'children_mismatch',
                        null,
                        'Guests aged '.$settings->childMinAge.'–'.$settings->childMaxAge.': '.$kids
                            .' · priced as children: '.$booking->children.' — check the quote.',
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * @return array{complete_count: int, total: int, png_known_total: int, png_pending_count: int}
     */
    public function summary(Booking $booking): array
    {
        $booking->loadMissing('guests');
        $guests = $booking->guests;

        return [
            'complete_count' => $guests->filter(fn (Guest $guest): bool => $guest->isComplete())->count(),
            'total' => $guests->count(),
            'png_known_total' => (int) $guests->sum(fn (Guest $guest): int => $guest->png_fee ?? 0),
            'png_pending_count' => $guests
                ->filter(fn (Guest $guest): bool => $guest->png_category === PngCategory::Pending)
                ->count(),
        ];
    }

    private function isConfirmedOrLater(Booking $booking): bool
    {
        return in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::FullyPaid,
            BookingStatus::OnBoard,
            BookingStatus::Completed,
            BookingStatus::Overdue,
        ], true);
    }

    /**
     * @return array{severity: string, code: string, guest_id: int|null, message: string}
     */
    private function issue(string $severity, string $code, ?int $guestId, string $message): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'guest_id' => $guestId,
            'message' => $message,
        ];
    }
}
