<?php

declare(strict_types=1);

use Syriable\UserProfile\Database\Seeders\LanguageSeeder;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Models\Language;

beforeEach(function (): void {
    $this->seed(LanguageSeeder::class);
});

it('finds languages by exact name, native name and codes', function (string $term): void {
    expect(UserProfile::searchLanguages($term, partial: false)->pluck('code')->all())->toBe(['ar']);
})->with(['Arabic', 'arabic', 'العربية', 'ar', 'ara', 'AR']);

it('matches names partially', function (): void {
    expect(UserProfile::searchLanguages('ish')->pluck('name')->all())
        ->toBe(['Danish', 'English', 'Finnish', 'Kurdish', 'Polish', 'Spanish', 'Swedish', 'Turkish']);
});

it('ranks code matches above name matches', function (): void {
    // "ar" is Arabic's code and also part of "Armenian", "Amharic" and "Magyar".
    expect(UserProfile::searchLanguages('ar')->pluck('code')->all())->toBe(['ar', 'hy', 'am', 'hu'])
        // "ind" is Indonesian's ISO 639-3 code and part of "Hindi".
        ->and(UserProfile::searchLanguages('ind')->pluck('name')->all())->toBe(['Indonesian', 'Hindi']);
});

it('does not match codes partially', function (): void {
    expect(UserProfile::searchLanguages('ar', partial: false)->pluck('code')->all())->toBe(['ar'])
        ->and(UserProfile::searchLanguages('arab', partial: false)->count())->toBe(0);
});

it('excludes inactive languages unless requested', function (): void {
    Language::query()->where('code', 'ar')->update(['is_active' => false]);

    expect(UserProfile::searchLanguages('arabic')->count())->toBe(0)
        ->and(UserProfile::searchLanguages('arabic', includeInactive: true)->count())->toBe(1);
});

it('returns no results for empty input', function (): void {
    expect(UserProfile::searchLanguages('  ')->count())->toBe(0);
});

it('is idempotent when seeding twice', function (): void {
    $count = Language::query()->count();

    $this->seed(LanguageSeeder::class);

    expect(Language::query()->count())->toBe($count);
});
