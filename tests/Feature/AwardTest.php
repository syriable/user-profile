<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Syriable\UserProfile\Models\Award;

it('stores multiple awards per user from different issuers', function (): void {
    $user = user();

    $user->awards()->create(['title' => 'Excellence Award', 'issuer' => 'Example Organization', 'date_received' => '2023-05-01']);
    $user->awards()->create(['title' => 'Hackathon Winner', 'issuer' => 'Laravel Live']);

    expect($user->awards()->orderBy('title')->pluck('issuer')->all())->toBe(['Example Organization', 'Laravel Live']);
});

it('only requires a title', function (): void {
    $award = user()->awards()->create(['title' => 'Dean\'s List']);

    expect($award->fresh())
        ->issuer->toBeNull()
        ->date_received->toBeNull()
        ->description->toBeNull()
        ->url->toBeNull();
});

it('stores optional dates, descriptions and links', function (): void {
    $award = user()->awards()->create([
        'title' => 'Open Source Award',
        'date_received' => '2024-11-20',
        'description' => 'For maintaining community packages.',
        'url' => 'https://example.com/awards/2024',
    ]);

    expect($award->date_received?->toDateString())->toBe('2024-11-20')
        ->and($award->description)->toBe('For maintaining community packages.')
        ->and($award->url)->toBe('https://example.com/awards/2024');
});

it('validates awards', function (array $attributes): void {
    user()->awards()->create($attributes);
})->with([
    'missing title' => [['issuer' => 'Org']],
    'invalid url' => [['title' => 'Award', 'url' => 'javascript:alert(1)']],
])->throws(ValidationException::class);

it('exposes rules that validate raw input before it reaches the model', function (): void {
    // Date casts parse on assignment, so malformed dates must be caught by
    // validating request input with the model's rules first.
    $validator = Validator::make(['title' => 'Award', 'date_received' => 'yesterday-ish'], (new Award)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toBe(['date_received']);
});

it('keeps awards separate from certifications', function (): void {
    $user = user();
    $user->awards()->create(['title' => 'Award']);

    expect($user->certifications()->count())->toBe(0)
        ->and(Award::query()->count())->toBe(1);
});
