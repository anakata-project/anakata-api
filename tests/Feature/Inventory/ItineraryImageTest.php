<?php

declare(strict_types=1);

use App\Actions\Itineraries\ReplaceItineraryImage;
use App\Models\ChangeHistory;
use App\Models\Itinerary;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    Storage::fake('public');
});

test('mateo can upload a hero image', function (): void {
    $itinerary = Itinerary::factory()->create();
    $mateo = managerUser();

    $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

    $url = $this->actingAs($mateo)
        ->post("/api/rms/itineraries/{$itinerary->id}/image", ['image' => $file], [
            'Accept' => 'application/json',
        ])
        ->assertOk()
        ->json('hero_image_url');

    expect($url)->toBeString()->toContain('/storage/itineraries/');

    $itinerary->refresh();
    expect($itinerary->hero_image_path)->toBeString();
    Storage::disk('public')->assertExists((string) $itinerary->hero_image_path);
    expect(ChangeHistory::query()->where('event', 'itinerary.image_replaced')->count())->toBe(1);
});

test('image upload rejects files that are too large or the wrong type', function (): void {
    $itinerary = Itinerary::factory()->create();
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->post("/api/rms/itineraries/{$itinerary->id}/image", [
            'image' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);

    $this->actingAs($mateo)
        ->post("/api/rms/itineraries/{$itinerary->id}/image", [
            'image' => UploadedFile::fake()->image('huge.jpg')->size(5000),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('replacing an image deletes the previous file after commit', function (): void {
    $itinerary = Itinerary::factory()->create();
    $mateo = managerUser();

    Storage::disk('public')->put('itineraries/old.jpg', 'old-bytes');
    $itinerary->forceFill(['hero_image_path' => 'itineraries/old.jpg'])->save();

    $this->actingAs($mateo)
        ->post("/api/rms/itineraries/{$itinerary->id}/image", [
            'image' => UploadedFile::fake()->image('new.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    Storage::disk('public')->assertMissing('itineraries/old.jpg');
    Storage::disk('public')->assertExists((string) $itinerary->fresh()?->hero_image_path);
});

test('a forced failure after store leaves the old file and path intact', function (): void {
    $itinerary = Itinerary::factory()->create();
    Storage::disk('public')->put('itineraries/old.jpg', 'old-bytes');
    $itinerary->forceFill(['hero_image_path' => 'itineraries/old.jpg'])->save();

    $filesBefore = Storage::disk('public')->allFiles('itineraries');

    $action = new class extends ReplaceItineraryImage
    {
        protected function afterFileStored(string $path): void
        {
            throw new RuntimeException('forced failure');
        }
    };

    $file = UploadedFile::fake()->image('new.jpg');

    expect(fn () => $action->handle($itinerary->fresh() ?? $itinerary, $file))
        ->toThrow(RuntimeException::class, 'forced failure');

    $itinerary->refresh();
    expect($itinerary->hero_image_path)->toBe('itineraries/old.jpg');
    Storage::disk('public')->assertExists('itineraries/old.jpg');
    expect(Storage::disk('public')->allFiles('itineraries'))->toBe($filesBefore);
});

test('lucia cannot upload an image', function (): void {
    $itinerary = Itinerary::factory()->create();
    $lucia = salesExecUser();

    $this->actingAs($lucia)
        ->post("/api/rms/itineraries/{$itinerary->id}/image", [
            'image' => UploadedFile::fake()->image('hero.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();
});
