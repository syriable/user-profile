<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Syriable\UserProfile\Exceptions\DuplicateProfileEntry;
use Syriable\UserProfile\Exceptions\InvalidProficiency;
use Syriable\UserProfile\Exceptions\ProfileEntryNotFound;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\ProfileLanguage;

function arabic(): Language
{
    return Language::query()->create([
        'name' => 'Arabic',
        'native_name' => 'العربية',
        'code' => 'ar',
        'iso_639_1' => 'ar',
        'iso_639_3' => 'ara',
    ]);
}

describe('catalog', function (): void {
    it('creates language records with normalized search columns', function (): void {
        $language = arabic();

        expect($language->fresh())
            ->name->toBe('Arabic')
            ->normalized_name->toBe('arabic')
            ->native_name->toBe('العربية')
            ->is_active->toBeTrue();
    });

    it('supports languages without a two-letter code', function (): void {
        $cantonese = Language::query()->create(['name' => 'Cantonese', 'code' => 'yue', 'iso_639_3' => 'yue']);

        expect($cantonese->iso_639_1)->toBeNull();
    });

    it('supports regional and script subtags in the code with canonical casing', function (): void {
        $language = Language::query()->create(['name' => 'Traditional Chinese (Taiwan)', 'code' => 'ZH-hant-tw']);

        expect($language->code)->toBe('zh-Hant-TW')
            ->and(user()->addLanguage('zh-hant-tw')->language_id)->toBe($language->id);
    });

    it('prevents duplicate languages with different spellings or codes', function (array $attributes): void {
        arabic();

        Language::query()->create($attributes);
    })->with([
        'same name, different case' => [['name' => '  ARABIC ', 'code' => 'ar-x']],
        'same code' => [['name' => 'Arabic (Standard)', 'code' => 'ar']],
        'same code, different case' => [['name' => 'Arabic (Standard)', 'code' => 'AR']],
        'same ISO 639-3' => [['name' => 'Modern Arabic', 'code' => 'arb', 'iso_639_3' => 'ara']],
    ])->throws(ValidationException::class);

    it('validates language identifiers', function (array $attributes): void {
        Language::query()->create(['name' => 'Test'] + $attributes);
    })->with([
        'invalid code' => [['code' => 'not a code']],
        'three letter ISO 639-1' => [['code' => 'tst', 'iso_639_1' => 'tst']],
        'two letter ISO 639-3' => [['code' => 'tst', 'iso_639_3' => 'ts']],
    ])->throws(ValidationException::class);

    it('lets applications add custom languages', function (): void {
        $language = Language::query()->create(['name' => 'Klingon', 'native_name' => 'tlhIngan Hol', 'code' => 'tlh', 'iso_639_3' => 'tlh']);

        user()->addLanguage($language, 'beginner');

        expect($language->profileLanguages()->count())->toBe(1);
    });
});

describe('user languages', function (): void {
    it('attaches languages with proficiency and flags', function (): void {
        $user = user();
        $english = Language::query()->create(['name' => 'English', 'code' => 'en']);

        $pivot = $user->addLanguage(arabic(), isNative: true, isPrimary: true);
        $user->addLanguage($english, 'advanced');

        expect($pivot)->toBeInstanceOf(ProfileLanguage::class)
            ->and($pivot->is_native)->toBeTrue()
            ->and($pivot->is_primary)->toBeTrue()
            ->and($pivot->proficiency_level)->toBeNull()
            ->and($user->languages()->count())->toBe(2)
            ->and($user->languages()->where('code', 'en')->first()?->pivot->proficiency_level)->toBe('advanced');
    });

    it('resolves languages by model, id or code', function (): void {
        $user = user();
        $language = arabic();

        $user->addLanguage('AR');

        expect($user->hasLanguage($language))->toBeTrue()
            ->and($user->hasLanguage($language->id))->toBeTrue()
            ->and($user->hasLanguage('ar'))->toBeTrue()
            ->and($user->hasLanguage('xx'))->toBeFalse();
    });

    it('throws when the language is not in the catalog', function (): void {
        user()->addLanguage('xx');
    })->throws(ProfileEntryNotFound::class, 'No Language matches [xx].');

    it('updates proficiency levels', function (): void {
        $user = user();
        $user->addLanguage(arabic(), 'intermediate');

        $pivot = $user->updateLanguage('ar', ['proficiency_level' => 'proficient']);

        expect($pivot->proficiency_level)->toBe('proficient')
            ->and($pivot->proficiencyLabel())->toBe('Proficient');
    });

    it('can clear a proficiency level when it is optional', function (): void {
        $user = user();
        $user->addLanguage(arabic(), 'intermediate');

        expect($user->updateLanguage('ar', ['proficiency_level' => null])->proficiency_level)->toBeNull();
    });

    it('validates proficiency levels', function (): void {
        user()->addLanguage(arabic(), 'fluent-ish');
    })->throws(InvalidProficiency::class);

    it('requires a proficiency level when configured', function (): void {
        config()->set('user-profile.languages.require_proficiency', true);

        user()->addLanguage(arabic());
    })->throws(InvalidProficiency::class, 'is required');

    it('applies the configured default proficiency', function (): void {
        config()->set('user-profile.languages.default_proficiency', 'beginner');

        expect(user()->addLanguage(arabic())->proficiency_level)->toBe('beginner');
    });

    it('rejects unknown pivot attributes on update', function (): void {
        $user = user();
        $user->addLanguage(arabic());

        $user->updateLanguage('ar', ['user_id' => 99]);
    })->throws(InvalidArgumentException::class, 'Unknown profile attribute(s) [user_id]');

    it('keeps a single primary language per user', function (): void {
        $user = user();
        $english = Language::query()->create(['name' => 'English', 'code' => 'en']);

        $user->addLanguage(arabic(), isPrimary: true);
        $user->addLanguage($english, isPrimary: true);

        expect($user->languages()->wherePivot('is_primary', true)->pluck('code')->all())->toBe(['en']);

        $user->updateLanguage('ar', ['is_primary' => true]);

        expect($user->languages()->wherePivot('is_primary', true)->pluck('code')->all())->toBe(['ar']);
    });

    it('prevents duplicate user-language associations', function (): void {
        $user = user();
        $user->addLanguage(arabic());

        $user->addLanguage('ar', 'advanced');
    })->throws(DuplicateProfileEntry::class);

    it('enforces uniqueness at the database level', function (): void {
        $user = user();
        $language = arabic();
        $user->languages()->attach($language);

        $user->languages()->attach($language);
    })->throws(QueryException::class);

    it('throws when updating a language that is not on the profile', function (): void {
        arabic();

        user()->updateLanguage('ar', ['is_native' => true]);
    })->throws(ProfileEntryNotFound::class);

    it('removes a language without deleting the catalog record', function (): void {
        $user = user();
        $user->addLanguage(arabic());

        expect($user->removeLanguage('ar'))->toBeTrue()
            ->and($user->removeLanguage('ar'))->toBeFalse()
            ->and($user->languages()->count())->toBe(0)
            ->and(Language::query()->where('code', 'ar')->exists())->toBeTrue();
    });

    it('finds users by language and minimum proficiency, counting native speakers', function (): void {
        $native = user('Native');
        $advanced = user('Advanced');
        $beginner = user('Beginner');
        $language = arabic();

        $native->addLanguage($language, isNative: true);
        $advanced->addLanguage($language, 'advanced');
        $beginner->addLanguage($language, 'beginner');

        $names = fn (?string $level) => $native->newQuery()->whereLanguage('ar', $level)->orderBy('name')->pluck('name')->all();

        expect($names(null))->toBe(['Advanced', 'Beginner', 'Native'])
            ->and($names('advanced'))->toBe(['Advanced', 'Native']);
    });
});
