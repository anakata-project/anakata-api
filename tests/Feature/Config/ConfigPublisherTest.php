<?php

declare(strict_types=1);

use App\Enums\ConfigKind;
use App\Events\ConfigPublished;
use App\Exceptions\ConflictException;
use App\Models\ChangeHistory;
use App\Services\Config\ConfigPublisher;
use App\Services\Config\CurrentConfig;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PDOException;
use Tests\Support\Config\TestConfigVersion;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    registerTestConfig();
});

test('publishing creates a new version, change list, history row and event', function (): void {
    Event::fake([ConfigPublished::class]);

    $actor = adminUser();
    $publisher = app(ConfigPublisher::class);

    $first = $publisher->publish(
        ConfigKind::Rates,
        testConfigDocument(),
        0,
        'APPROVAL-1',
        $actor,
    );

    expect($first->version)->toBe(1);
    expect($first->changes)->not->toBe([]);

    $second = $publisher->publish(
        ConfigKind::Rates,
        testConfigDocument(['terms' => ['cabin_deposit_pct' => 15]]),
        1,
        'BOARD-22',
        $actor,
    );

    expect($second->version)->toBe(2);
    expect($second->approval_reference)->toBe('BOARD-22');
    expect($second->changes)->toBe([
        [
            'path' => 'terms.cabin_deposit_pct',
            'label' => 'Cabin deposit %',
            'from' => 10,
            'to' => 15,
        ],
    ]);

    $entry = ChangeHistory::query()
        ->where('event', 'rates.published')
        ->where('subject_id', $second->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry?->reason)->toBe('BOARD-22');
    expect($entry?->subject_type)->toBe('test_config_version');
    expect($entry?->after)->toMatchArray(['version' => 2, 'changes' => 1]);

    Event::assertDispatched(ConfigPublished::class, function (ConfigPublished $event) use ($second): bool {
        return $event->kind === ConfigKind::Rates && $event->version->is($second);
    });
});

test('a stale base version is a 409 and writes nothing', function (): void {
    $actor = adminUser();
    $publisher = app(ConfigPublisher::class);

    $publisher->publish(ConfigKind::Rates, testConfigDocument(), 0, 'A', $actor);

    expect(fn () => $publisher->publish(
        ConfigKind::Rates,
        testConfigDocument(['title' => 'Moved']),
        0,
        'B',
        $actor,
    ))->toThrow(
        ConflictException::class,
        'Someone published a newer version (v1) while you were editing. Reload to see it; your changes were not saved.',
    );

    expect(TestConfigVersion::query()->count())->toBe(1);
});

test('validation errors are keyed under document paths', function (): void {
    $actor = adminUser();

    try {
        app(ConfigPublisher::class)->publish(
            ConfigKind::Rates,
            testConfigDocument(['terms' => ['cabin_deposit_pct' => 200]]),
            0,
            'A',
            $actor,
        );
        expect(false)->toBeTrue('expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('document.terms.cabin_deposit_pct');
    }

    expect(TestConfigVersion::query()->count())->toBe(0);
});

test('an unchanged document is a 422', function (): void {
    $actor = adminUser();
    $publisher = app(ConfigPublisher::class);
    $document = testConfigDocument();

    $publisher->publish(ConfigKind::Rates, $document, 0, 'A', $actor);

    try {
        $publisher->publish(ConfigKind::Rates, $document, 1, 'B', $actor);
        expect(false)->toBeTrue('expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors()['document'][0] ?? null)->toBe(
            'Nothing to publish — the document is identical to the published version.',
        );
    }

    expect(TestConfigVersion::query()->count())->toBe(1);
});

test('the same document after a database round-trip with reordered keys is a 422', function (): void {
    $actor = adminUser();
    $publisher = app(ConfigPublisher::class);

    $publisher->publish(ConfigKind::Rates, testConfigDocument(), 0, 'A', $actor);

    $stored = TestConfigVersion::query()->firstOrFail();
    $fresh = $stored->asDocument()->toArray();

    $reordered = [
        'bands' => $fresh['bands'],
        'title' => $fresh['title'],
        'terms' => [
            'cabin_deposit_pct' => $fresh['terms']['cabin_deposit_pct'],
        ],
    ];

    try {
        $publisher->publish(ConfigKind::Rates, $reordered, 1, 'B', $actor);
        expect(false)->toBeTrue('expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors()['document'][0] ?? null)->toBe(
            'Nothing to publish — the document is identical to the published version.',
        );
    }
});

test('a missing approval reference is a 422 when required', function (): void {
    $actor = adminUser();

    try {
        app(ConfigPublisher::class)->publish(
            ConfigKind::Rates,
            testConfigDocument(),
            0,
            null,
            $actor,
        );
        expect(false)->toBeTrue('expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('approval_reference');
    }
});

test('version rows cannot be updated or deleted', function (): void {
    $actor = adminUser();
    $version = app(ConfigPublisher::class)->publish(
        ConfigKind::Rates,
        testConfigDocument(),
        0,
        'A',
        $actor,
    );

    $version->approval_reference = 'tampered';
    expect(fn () => $version->save())->toThrow(LogicException::class, 'Configuration versions cannot be updated.');
    expect(fn () => $version->update(['approval_reference' => 'tampered']))->toThrow(
        LogicException::class,
        'Configuration versions cannot be updated.',
    );
    expect(fn () => $version->delete())->toThrow(LogicException::class, 'Configuration versions cannot be deleted.');
});

test('a unique version collision is a 409 from a fresh read, not the failed transaction', function (): void {
    $actor = adminUser();
    $publisher = app(ConfigPublisher::class);

    $publisher->publish(ConfigKind::Rates, testConfigDocument(), 0, 'A', $actor);

    TestConfigVersion::query()->create([
        'version' => 2,
        'document' => testConfigDocument(['title' => 'Winner']),
        'changes' => [
            [
                'path' => 'title',
                'label' => 'Title',
                'from' => 'Cabin terms',
                'to' => 'Winner',
            ],
        ],
        'approval_reference' => 'WIN',
        'published_at' => now(),
    ]);

    TestConfigVersion::creating(function (): void {
        throw new UniqueConstraintViolationException(
            'mysql',
            'insert into test_config_versions',
            [],
            new PDOException('Duplicate entry', 23000),
        );
    });

    expect(fn () => $publisher->publish(
        ConfigKind::Rates,
        testConfigDocument(['title' => 'Loser']),
        2,
        'LOSE',
        $actor,
    ))->toThrow(
        ConflictException::class,
        'Someone published a newer version (v2) while you were editing. Reload to see it; your changes were not saved.',
    );

    expect(TestConfigVersion::query()->where('approval_reference', 'LOSE')->exists())->toBeFalse();
    expect(TestConfigVersion::query()->where('version', 2)->value('approval_reference'))->toBe('WIN');
});

test('CurrentConfig is cached and cleared after a publish', function (): void {
    $actor = adminUser();
    $publisher = app(ConfigPublisher::class);

    $publisher->publish(ConfigKind::Rates, testConfigDocument(), 0, 'A', $actor);

    $current = app(CurrentConfig::class);
    expect($current->version(ConfigKind::Rates)->version)->toBe(1);
    expect($current->document(ConfigKind::Rates)->toArray()['title'])->toBe('Cabin terms');

    $publisher->publish(
        ConfigKind::Rates,
        testConfigDocument(['title' => 'Updated terms']),
        1,
        'B',
        $actor,
    );

    expect($current->version(ConfigKind::Rates)->version)->toBe(2);
    expect($current->document(ConfigKind::Rates)->toArray()['title'])->toBe('Updated terms');
});

test('a System publish has a null created_by and a System history row', function (): void {
    $version = app(ConfigPublisher::class)->publish(
        ConfigKind::Rates,
        testConfigDocument(),
        0,
        'SPRINT-SYSTEM',
        null,
    );

    expect($version->created_by)->toBeNull();
    expect($version->updated_by)->toBeNull();

    $entry = ChangeHistory::query()
        ->where('event', 'rates.published')
        ->where('subject_id', $version->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry?->actor_id)->toBeNull();
    expect($entry?->actor_label)->toBe('System');
    expect($entry?->reason)->toBe('SPRINT-SYSTEM');
});
