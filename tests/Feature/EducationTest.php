<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Syriable\UserProfile\Models\Education;

it('stores multiple education records per user', function (): void {
    $user = user();

    $user->educations()->create(['institution_name' => 'Damascus University', 'degree' => 'Bachelor', 'field_of_study' => 'Informatics', 'graduation_year' => 2016]);
    $user->educations()->create(['institution_name' => 'TU Berlin', 'degree' => 'Master', 'country_code' => 'de', 'city' => 'Berlin']);

    expect($user->educations()->count())->toBe(2)
        ->and($user->educations()->where('degree', 'Master')->value('country_code'))->toBe('DE');
});

it('only requires the institution name', function (): void {
    $education = user()->educations()->create(['institution_name' => 'Online Academy']);

    expect($education->fresh())
        ->degree->toBeNull()
        ->field_of_study->toBeNull()
        ->start_date->toBeNull()
        ->graduation_year->toBeNull()
        ->is_current->toBeFalse();

    expect(fn () => user('Other')->educations()->create(['degree' => 'Bachelor']))->toThrow(ValidationException::class);
});

it('supports non-degree education and an application-defined type', function (): void {
    $education = user()->educations()->create(['institution_name' => 'Laracasts', 'type' => 'online_course', 'field_of_study' => 'Laravel']);

    expect($education->type)->toBe('online_course')
        ->and($education->degree)->toBeNull();
});

it('supports ongoing studies without a graduation date', function (): void {
    $education = user()->educations()->create([
        'institution_name' => 'Example University',
        'start_date' => '2024-09-01',
        'is_current' => true,
    ]);

    expect($education->is_current)->toBeTrue()
        ->and($education->end_date)->toBeNull()
        ->and($education->graduation_year)->toBeNull();
});

it('allows an expected graduation year for ongoing studies', function (): void {
    $education = user()->educations()->create([
        'institution_name' => 'Example University',
        'start_date' => '2024-09-01',
        'graduation_year' => 2028,
        'is_current' => true,
    ]);

    expect($education->graduation_year)->toBe(2028);
});

it('rejects an end date on ongoing studies', function (): void {
    user()->educations()->create([
        'institution_name' => 'Example University',
        'end_date' => '2025-06-30',
        'is_current' => true,
    ]);
})->throws(ValidationException::class, 'end date field is prohibited');

it('derives the graduation year from the end date', function (): void {
    $education = user()->educations()->create([
        'institution_name' => 'Example University',
        'start_date' => '2016-09-01',
        'end_date' => '2020-06-30',
        'graduation_year' => 1999,
    ]);

    expect($education->graduation_year)->toBe(2020);

    $education->update(['end_date' => '2021-02-01']);

    expect($education->fresh()?->graduation_year)->toBe(2021);
});

it('stores a graduation year on its own when only the year is known', function (): void {
    $education = user()->educations()->create(['institution_name' => 'Example University', 'graduation_year' => 2024]);

    expect($education->graduation_year)->toBe(2024)
        ->and($education->end_date)->toBeNull();
});

it('validates date consistency', function (array $attributes, string $message): void {
    expect(fn () => user()->educations()->create(['institution_name' => 'Example University'] + $attributes))
        ->toThrow(ValidationException::class, $message);
})->with([
    'end before start' => [['start_date' => '2020-09-01', 'end_date' => '2019-06-30'], 'end date field must be a date after or equal to start date'],
    'graduation before start' => [['start_date' => '2020-09-01', 'graduation_year' => 2018], 'graduation year cannot be before the start date'],
    'implausible year' => [['graduation_year' => 1800], 'graduation year field must be between'],
    'invalid country' => [['country_code' => 'Germany'], 'country code field format is invalid'],
]);

it('orders history with current studies first', function (): void {
    $user = user();
    $user->educations()->create(['institution_name' => 'Old', 'graduation_year' => 2010]);
    $user->educations()->create(['institution_name' => 'Undated']);
    $user->educations()->create(['institution_name' => 'Now', 'is_current' => true]);
    $user->educations()->create(['institution_name' => 'Recent', 'graduation_year' => 2020]);

    expect($user->educations()->latestFirst()->pluck('institution_name')->all())->toBe(['Now', 'Recent', 'Old', 'Undated']);
});

it('exposes its validation rules for form requests', function (): void {
    expect((new Education)->rules())->toHaveKeys(['institution_name', 'start_date', 'end_date', 'graduation_year']);
});
