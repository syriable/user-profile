<?php

declare(strict_types=1);

use Syriable\UserProfile\Enums\ProficiencyType;
use Syriable\UserProfile\Exceptions\InvalidProficiency;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Support\ProficiencyScale;

it('reads the default skill scale from configuration', function (): void {
    $scale = UserProfile::skillProficiency();

    expect($scale->type)->toBe(ProficiencyType::Skill)
        ->and($scale->levels)->toBe(['beginner', 'intermediate', 'advanced', 'expert'])
        ->and($scale->default)->toBeNull()
        ->and($scale->required)->toBeFalse();
});

it('does not treat native as a language proficiency level', function (): void {
    expect(UserProfile::languageProficiency()->has('native'))->toBeFalse();
});

it('ranks and compares levels by their position', function (): void {
    $scale = UserProfile::skillProficiency();

    expect($scale->rank('beginner'))->toBe(0)
        ->and($scale->rank('expert'))->toBe(3)
        ->and($scale->compare('advanced', 'intermediate'))->toBe(1)
        ->and($scale->compare('beginner', 'expert'))->toBe(-1)
        ->and($scale->compare('expert', 'expert'))->toBe(0)
        ->and($scale->atLeast('advanced'))->toBe(['advanced', 'expert']);
});

it('rejects unknown levels', function (): void {
    UserProfile::skillProficiency()->rank('guru');
})->throws(InvalidProficiency::class, 'The skills proficiency level [guru] is not valid.');

it('provides translated labels and falls back to a headline', function (): void {
    $scale = new ProficiencyScale(ProficiencyType::Skill, ['beginner', 'world_class']);

    expect($scale->label('beginner'))->toBe('Beginner')
        ->and($scale->label('world_class'))->toBe('World Class')
        ->and($scale->options())->toBe(['beginner' => 'Beginner', 'world_class' => 'World Class']);
});

it('supports a CEFR scale configured by the application', function (): void {
    config()->set('user-profile.languages.proficiency_levels', ['a1', 'a2', 'b1', 'b2', 'c1', 'c2']);

    $scale = UserProfile::languageProficiency();

    expect($scale->label('b2'))->toBe('B2 – Upper intermediate')
        ->and($scale->has('advanced'))->toBeFalse()
        ->and($scale->atLeast('c1'))->toBe(['c1', 'c2']);
});

it('resolves the configured default and required levels', function (): void {
    $optional = new ProficiencyScale(ProficiencyType::Skill, ['low', 'high']);
    $defaulted = new ProficiencyScale(ProficiencyType::Skill, ['low', 'high'], default: 'low');
    $required = new ProficiencyScale(ProficiencyType::Skill, ['low', 'high'], required: true);

    expect($optional->resolve(null))->toBeNull()
        ->and($defaulted->resolve(null))->toBe('low')
        ->and($defaulted->resolve('high'))->toBe('high')
        ->and(fn () => $required->resolve(null))->toThrow(InvalidProficiency::class, 'is required');
});

it('rejects invalid scale definitions', function (array $levels, ?string $default): void {
    new ProficiencyScale(ProficiencyType::Skill, $levels, $default);
})->with([
    'empty' => [[], null],
    'duplicates' => [['low', 'low'], null],
    'unknown default' => [['low', 'high'], 'medium'],
])->throws(InvalidArgumentException::class);
