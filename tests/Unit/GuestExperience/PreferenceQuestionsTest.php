<?php

declare(strict_types=1);

use App\Support\GuestExperience\PreferenceQuestions;

test('the questionnaire is the prototype list, with two restricted questions and no Kosher option', function (): void {
    $questions = PreferenceQuestions::all();

    expect(array_map(fn ($question): string => $question->key, $questions))->toBe([
        'diet', 'breakfast', 'pillow', 'temp', 'bev', 'intensity', 'time', 'interests', 'celebr', 'access', 'emerg', 'first', 'req',
    ]);

    expect(collect($questions)->every(fn ($question): bool => $question->required === false))->toBeTrue();
    expect(PreferenceQuestions::find('access')?->restricted)->toBeTrue();
    expect(PreferenceQuestions::find('emerg')?->restricted)->toBeTrue();
    expect(collect($questions)->filter(fn ($question): bool => $question->restricted)->count())->toBe(2);

    $options = collect($questions)->flatMap(fn ($question): array => $question->options)->all();
    expect($options)->not->toContain('Kosher');
});
