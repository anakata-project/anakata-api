<?php

declare(strict_types=1);

use App\Actions\Documents\PrepareIssueDocument;
use App\Actions\Documents\SendDocument;
use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\DocumentKind;
use App\Jobs\SendDeliveryJob;
use App\Mail\Documents\DocumentMail;
use App\Models\Booking;
use App\Models\ChangeHistory;
use App\Models\Delivery;
use App\Support\Automations\AutomationGate;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

afterEach(function (): void {
    Storage::disk('documents')->deleteDirectory('/');
});

function sendableBooking(): Booking
{
    $departure = ReservationFixtures::anamaraDeparture('2028-11-05');

    return Booking::factory()->create([
        'departure_id' => $departure->id,
        'cabin_id' => $departure->yacht->cabins->firstWhere('code', 'S6')?->id,
        'status' => BookingStatus::Confirmed,
        'reference' => 'ANK-2026-5100',
    ]);
}

test('the same key twice sends once', function (): void {
    Mail::fake();
    $actor = adminUser();
    $booking = sendableBooking();
    $document = app(PrepareIssueDocument::class)->handle($booking, DocumentKind::Invoice, actor: $actor);

    $first = app(SendDocument::class)->handle($booking, $document, $actor);
    $second = app(SendDocument::class)->handle($booking, $document, $actor);

    expect($first->id)->toBe($second->id);
    expect(Delivery::query()->count())->toBe(1);
    Mail::assertSent(DocumentMail::class, 1);
});

test('a staff resend gets its own row and sends again', function (): void {
    Mail::fake();
    $actor = adminUser();
    $booking = sendableBooking();
    $document = app(PrepareIssueDocument::class)->handle($booking, DocumentKind::Invoice, actor: $actor);

    $first = app(SendDocument::class)->handle($booking, $document, $actor);
    $resend = app(SendDocument::class)->handle($booking, $document, $actor, resend: true);

    expect($resend->id)->not->toBe($first->id);
    expect($resend->idempotency_key)->toStartWith('resend:');
    expect(Delivery::query()->count())->toBe(2);
    Mail::assertSent(DocumentMail::class, 2);
});

test('the attached PDF is byte-identical to the stored file', function (): void {
    Mail::fake();
    $actor = adminUser();
    $booking = sendableBooking();
    $document = app(PrepareIssueDocument::class)->handle($booking, DocumentKind::Summary, actor: $actor);

    app(SendDocument::class)->handle($booking, $document, $actor);

    Mail::assertSent(DocumentMail::class, function (DocumentMail $mail) use ($document): bool {
        return hash('sha256', $mail->pdfBytes) === $document->file_sha256;
    });
});

test('the job retries three times with backoff', function (): void {
    $job = new SendDeliveryJob(1);

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([60, 300]);
});

test('a transport that fails twice then succeeds ends SENT with one mail', function (): void {
    Queue::fake();
    $actor = adminUser();
    $booking = sendableBooking();
    $document = app(PrepareIssueDocument::class)->handle($booking, DocumentKind::Invoice, actor: $actor);
    $delivery = app(SendDocument::class)->handle($booking, $document, $actor);
    $delivery->refresh();

    expect($delivery->status)->toBe(DeliveryStatus::Queued);

    $attempts = 0;
    Mail::shouldReceive('send')->andReturnUsing(function () use (&$attempts): void {
        $attempts++;
        if ($attempts <= 2) {
            throw new TransportException('smtp down');
        }
    });

    $job = new SendDeliveryJob($delivery->id);

    try {
        $job->handle(app(AutomationGate::class));
    } catch (TransportException) {
    }
    expect($delivery->fresh()?->status)->toBe(DeliveryStatus::Queued);

    try {
        $job->handle(app(AutomationGate::class));
    } catch (TransportException) {
    }
    expect($delivery->fresh()?->status)->toBe(DeliveryStatus::Queued);
    expect(ChangeHistory::query()->where('event', 'document.send_failed')->count())->toBe(0);

    $job->handle(app(AutomationGate::class));

    expect($delivery->fresh()?->status)->toBe(DeliveryStatus::Sent);
    expect($attempts)->toBe(3);
    expect(ChangeHistory::query()->where('event', 'document.send_failed')->count())->toBe(0);
    expect(ChangeHistory::query()->where('event', 'document.sent')->count())->toBe(1);
});

test('a transport that always fails ends FAILED after three attempts', function (): void {
    Queue::fake();
    $actor = adminUser();
    $booking = sendableBooking();
    $document = app(PrepareIssueDocument::class)->handle($booking, DocumentKind::Invoice, actor: $actor);
    $delivery = app(SendDocument::class)->handle($booking, $document, $actor);

    Mail::shouldReceive('send')->andThrow(new TransportException('smtp down'));

    $job = new SendDeliveryJob($delivery->id);

    foreach ([1, 2] as $ignored) {
        try {
            $job->handle(app(AutomationGate::class));
        } catch (TransportException) {
        }
        expect($delivery->fresh()?->status)->toBe(DeliveryStatus::Queued);
    }

    expect(ChangeHistory::query()->where('event', 'document.send_failed')->count())->toBe(0);

    try {
        $job->handle(app(AutomationGate::class));
    } catch (TransportException $exception) {
        $job->failed($exception);
    }

    expect($delivery->fresh()?->status)->toBe(DeliveryStatus::Failed);
    expect($delivery->fresh()?->error)->toBe('smtp down');
    expect(ChangeHistory::query()->where('event', 'document.send_failed')->count())->toBe(1);
});
