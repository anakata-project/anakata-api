<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\SubjectRequest;
use App\Services\Config\CurrentConfig;
use App\Support\BusinessTime;
use App\Support\History\History;
use App\Support\Retention\RetentionWindow;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class RetentionCommand extends Command
{
    protected $signature = 'anakata:retention {--dry-run : Print counts and write nothing}';

    protected $description = 'Anonymise passport data and purge medical notes on the published retention schedule (B4)';

    public function handle(CurrentConfig $config): int
    {
        $rules = $config->businessRules()->retention;
        $months = $rules->passportMonthsAfterCruise;
        $days = $rules->medicalDaysAfterCruise;
        $today = BusinessTime::now()->toDateString();
        $dry = (bool) $this->option('dry-run');

        $passportGuests = 0;
        $noteGuests = 0;
        $changedBookings = 0;

        $this->candidates()->chunkById(100, function ($bookings) use (
            $months,
            $days,
            $today,
            $dry,
            &$passportGuests,
            &$noteGuests,
            &$changedBookings,
        ): void {
            foreach ($bookings as $booking) {
                $booking->loadMissing(['departure', 'guests']);
                $returnDate = $booking->departure->returnDate();
                $purgePassports = RetentionWindow::elapsed(
                    RetentionWindow::passportEndsOn($returnDate, $months),
                    $today,
                );
                $purgeNotes = RetentionWindow::elapsed(
                    RetentionWindow::notesEndOn($returnDate, $days),
                    $today,
                );

                if (! $purgePassports && ! $purgeNotes) {
                    continue;
                }

                $passports = 0;
                $notes = 0;

                foreach ($booking->guests as $guest) {
                    if ($purgePassports && $this->hasPassportData($guest)) {
                        $passports++;
                    }

                    if ($purgeNotes && $this->hasNotes($guest)) {
                        $notes++;
                    }
                }

                if ($passports === 0 && $notes === 0) {
                    continue;
                }

                $passportGuests += $passports;
                $noteGuests += $notes;
                $changedBookings++;

                if ($dry) {
                    continue;
                }

                DB::transaction(function () use ($booking, $purgePassports, $purgeNotes, $passports, $notes, $months, $days): void {
                    foreach ($booking->guests as $guest) {
                        $dirty = false;

                        if ($purgePassports && $this->hasPassportData($guest)) {
                            $guest->passport_no = null;
                            $guest->passport_expiry = null;
                            $dirty = true;
                        }

                        if ($purgeNotes && $this->hasNotes($guest)) {
                            $guest->medical_note = null;
                            $guest->dietary_note = null;
                            $guest->accessibility_note = null;
                            $dirty = true;
                        }

                        if ($dirty) {
                            $guest->save();
                        }
                    }

                    History::record($booking, 'retention.applied', after: [
                        'passports_anonymised' => $passports,
                        'notes_purged' => $notes,
                    ], extraContext: [
                        'what' => $this->historyWhat($passports, $notes, $months, $days),
                    ], system: true);
                });
            }
        });

        $exports = $this->expireExports($config, $dry);

        $verb = $dry ? 'Would change' : 'Changed';
        $this->info($verb.' '.$changedBookings.' booking(s): '.$passportGuests.' passport(s), '.$noteGuests.' note set(s).');
        $this->info(($dry ? 'Would delete ' : 'Deleted ').$exports.' access export(s).');

        return self::SUCCESS;
    }

    /**
     * @return Builder<Booking>
     */
    private function candidates(): Builder
    {
        return Booking::query()
            ->withTrashed()
            ->whereHas('guests', function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->whereNotNull('passport_no')
                        ->orWhereNotNull('passport_expiry')
                        ->orWhereNotNull('medical_note')
                        ->orWhereNotNull('dietary_note')
                        ->orWhereNotNull('accessibility_note');
                });
            })
            ->orderBy('id');
    }

    private function hasPassportData(Guest $guest): bool
    {
        return $guest->passport_no !== null || $guest->passport_expiry !== null;
    }

    private function hasNotes(Guest $guest): bool
    {
        return $guest->medical_note !== null
            || $guest->dietary_note !== null
            || $guest->accessibility_note !== null;
    }

    private function historyWhat(int $passports, int $notes, int $months, int $days): string
    {
        $parts = [];

        if ($passports > 0) {
            $parts[] = 'passport data anonymised for '.$passports.' guests ('.$months.' months after the cruise, B4)';
        }

        if ($notes > 0) {
            $parts[] = 'medical notes purged for '.$notes.' guests ('.$days.' days after the cruise, B4)';
        }

        return 'Retention — '.implode('; ', $parts);
    }

    private function expireExports(CurrentConfig $config, bool $dry): int
    {
        $days = $config->businessRules()->privacy->requestSlaDays;
        $cutoff = now()->subDays($days);
        $count = 0;

        SubjectRequest::query()
            ->whereNotNull('export_path')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->orderBy('id')
            ->each(function (SubjectRequest $request) use ($dry, &$count): void {
                $count++;

                if ($dry || $request->export_path === null) {
                    return;
                }

                Storage::disk('local')->delete($request->export_path);
                $request->forceFill(['export_path' => null])->save();
            });

        return $count;
    }
}
