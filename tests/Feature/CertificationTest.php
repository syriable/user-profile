<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('stores multiple certifications per user', function (): void {
    $user = user();

    $user->certifications()->create(['name' => 'Google Data Analytics', 'issuing_organization' => 'Google']);
    $user->certifications()->create(['name' => 'Azure Fundamentals', 'issuing_organization' => 'Microsoft', 'credential_id' => 'AZ-900-123']);

    expect($user->certifications()->count())->toBe(2);
});

it('handles missing optional data', function (): void {
    $certification = user()->certifications()->create(['name' => 'Professional Certificate', 'issuing_organization' => 'Example Organization']);

    expect($certification->fresh())
        ->issue_date->toBeNull()
        ->expiration_date->toBeNull()
        ->credential_id->toBeNull()
        ->credential_url->toBeNull()
        ->description->toBeNull();
});

it('stores optional credential identifiers and verification urls as provided', function (): void {
    $certification = user()->certifications()->create([
        'name' => 'Meta Front-End Developer',
        'issuing_organization' => 'Meta',
        'credential_id' => 'ABC-123',
        'credential_url' => 'https://www.coursera.org/verify/ABC-123',
    ]);

    expect($certification->credential_id)->toBe('ABC-123')
        ->and($certification->credential_url)->toBe('https://www.coursera.org/verify/ABC-123');
});

it('requires a name and an issuing organization', function (array $attributes): void {
    user()->certifications()->create($attributes);
})->with([
    [['issuing_organization' => 'Google']],
    [['name' => 'Certificate']],
])->throws(ValidationException::class);

it('rejects non-http verification urls', function (string $url): void {
    user()->certifications()->create(['name' => 'Certificate', 'issuing_organization' => 'Org', 'credential_url' => $url]);
})->with(['javascript:alert(1)', 'not a url', 'ftp://example.com/cert'])->throws(ValidationException::class);

it('rejects an expiration date before the issue date', function (): void {
    user()->certifications()->create([
        'name' => 'Certificate',
        'issuing_organization' => 'Org',
        'issue_date' => '2024-01-01',
        'expiration_date' => '2023-01-01',
    ]);
})->throws(ValidationException::class, 'expiration date field must be a date after or equal to issue date');

it('handles expiration', function (): void {
    Carbon::setTestNow('2026-06-15');
    $user = user();

    $never = $user->certifications()->create(['name' => 'Lifetime', 'issuing_organization' => 'Org']);
    $valid = $user->certifications()->create(['name' => 'Valid', 'issuing_organization' => 'Org', 'expiration_date' => '2027-01-01']);
    $today = $user->certifications()->create(['name' => 'Today', 'issuing_organization' => 'Org', 'expiration_date' => '2026-06-15']);
    $expired = $user->certifications()->create(['name' => 'Expired', 'issuing_organization' => 'Org', 'expiration_date' => '2025-01-01']);

    expect($never->expires())->toBeFalse()
        ->and($never->isExpired())->toBeFalse()
        ->and($valid->isExpired())->toBeFalse()
        ->and($today->isExpired())->toBeFalse()
        ->and($expired->isExpired())->toBeTrue()
        ->and($valid->isExpired(Carbon::parse('2027-01-02')))->toBeTrue()
        ->and($user->certifications()->valid()->orderBy('name')->pluck('name')->all())->toBe(['Lifetime', 'Today', 'Valid'])
        ->and($user->certifications()->expired()->pluck('name')->all())->toBe(['Expired']);

    Carbon::setTestNow();
});
