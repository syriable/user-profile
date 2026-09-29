<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Syriable\UserProfile\Exceptions\AmbiguousProfileEntry;
use Syriable\UserProfile\Exceptions\FeatureDisabled;
use Syriable\UserProfile\Exceptions\InvalidProficiency;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Tests\Fixtures\User;

/**
 * @return list<string>
 */
function ownerNames(Builder $query): array
{
    return $query->orderBy('name')->pluck('name')->all();
}

beforeEach(function (): void {
    $php = Skill::query()->create(['name' => 'PHP']);
    $laravel = Skill::query()->create(['name' => 'Laravel']);
    $javascript = Skill::query()->create(['name' => 'JavaScript']);
    $javascript->addAlias('JS');
    $javascript->addAlias('ECMAScript');
    Skill::query()->create(['name' => 'WordPress']);

    Language::query()->create(['name' => 'English', 'native_name' => 'English', 'code' => 'en', 'iso_639_3' => 'eng']);
    Language::query()->create(['name' => 'Arabic', 'native_name' => 'العربية', 'code' => 'ar', 'iso_639_3' => 'ara']);
    Language::query()->create(['name' => 'French', 'native_name' => 'Français', 'code' => 'fr']);

    // Alice: PHP (expert, primary) + Laravel (advanced) + JavaScript; English native, Arabic intermediate
    $alice = user('Alice');
    $alice->addSkill($php, 'expert', isPrimary: true);
    $alice->addSkill($laravel, 'advanced');
    $alice->addSkill($javascript, 'intermediate');
    $alice->addLanguage('en', isNative: true, isPrimary: true);
    $alice->addLanguage('ar', 'intermediate');

    // Bob: PHP (beginner) + WordPress; English advanced
    $bob = user('Bob');
    $bob->addSkill($php, 'beginner');
    $bob->addSkill('wordpress', 'expert', isPrimary: true);
    $bob->addLanguage('en', 'advanced');

    // Carol: JavaScript (expert); French native, English elementary
    $carol = user('Carol');
    $carol->addSkill('javascript', 'expert', isPrimary: true);
    $carol->addLanguage('fr', isNative: true, isPrimary: true);
    $carol->addLanguage('en', 'elementary');

    // Dave: empty profile
    user('Dave');
});

describe('skills', function (): void {
    it('filters by one skill', function (): void {
        expect(ownerNames(User::query()->whereSkill('PHP')))->toBe(['Alice', 'Bob'])
            ->and(ownerNames(User::query()->whereSkill('laravel')))->toBe(['Alice']);
    });

    it('accepts a model, an id, a name, a slug or an alias', function (mixed $skill): void {
        $skill = $skill instanceof Closure ? $skill() : $skill;

        expect(ownerNames(User::query()->whereSkill($skill)))->toBe(['Alice', 'Carol']);
    })->with([
        'model' => fn () => fn () => Skill::query()->where('name', 'JavaScript')->firstOrFail(),
        'id' => fn () => fn () => Skill::query()->where('name', 'JavaScript')->value('id'),
        'name' => ['JavaScript'],
        'normalized name' => ['  javascript '],
        'slug' => ['javascript'],
        'alias' => ['JS'],
        'alias, other case' => ['ecmascript'],
    ]);

    it('filters by any of several skills', function (): void {
        expect(ownerNames(User::query()->whereAnySkill(['Laravel', 'JS'])))->toBe(['Alice', 'Carol'])
            ->and(ownerNames(User::query()->whereAnySkill(['WordPress', 'Unknown'])))->toBe(['Bob']);
    });

    it('filters by all of several skills', function (): void {
        expect(ownerNames(User::query()->whereAllSkills(['PHP', 'Laravel'])))->toBe(['Alice'])
            ->and(ownerNames(User::query()->whereAllSkills(['PHP', 'JavaScript'])))->toBe(['Alice'])
            ->and(ownerNames(User::query()->whereAllSkills(['PHP', 'Unknown'])))->toBe([]);
    });

    it('excludes owners with a skill', function (): void {
        expect(ownerNames(User::query()->withoutSkill('WordPress')))->toBe(['Alice', 'Carol', 'Dave'])
            ->and(ownerNames(User::query()->whereSkill('PHP')->withoutSkill('WordPress')))->toBe(['Alice']);
    });

    it('filters by a minimum proficiency using the scale order, not alphabetical order', function (): void {
        // Alphabetically "advanced" < "beginner" < "expert" < "intermediate".
        expect(ownerNames(User::query()->whereSkill('PHP', atLeast: 'intermediate')))->toBe(['Alice'])
            ->and(ownerNames(User::query()->whereSkill('PHP', atLeast: 'beginner')))->toBe(['Alice', 'Bob'])
            ->and(ownerNames(User::query()->whereSkill('JavaScript', atLeast: 'advanced')))->toBe(['Carol'])
            ->and(ownerNames(User::query()->whereAnySkill(['PHP', 'JavaScript'], atLeast: 'expert')))->toBe(['Alice', 'Carol'])
            ->and(ownerNames(User::query()->whereAllSkills(['PHP', 'Laravel'], atLeast: 'advanced')))->toBe(['Alice']);
    });

    it('does not count unrated skills towards a proficiency threshold', function (): void {
        user('Eve')->addSkill('php');

        expect(ownerNames(User::query()->whereSkill('PHP', atLeast: 'beginner')))->toBe(['Alice', 'Bob']);
    });

    it('filters by primary skill', function (): void {
        expect(ownerNames(User::query()->whereSkill('PHP', primary: true)))->toBe(['Alice'])
            ->and(ownerNames(User::query()->whereSkill('PHP', primary: false)))->toBe(['Bob']);
    });

    it('rejects unknown proficiency levels before querying', function (): void {
        User::query()->whereSkill('PHP', atLeast: 'guru');
    })->throws(InvalidProficiency::class);

    it('uses an application-defined scale', function (): void {
        config()->set('user-profile.skills.proficiency_levels', ['beginner', 'intermediate', 'advanced', 'expert', 'master']);
        user('Frank')->addSkill('php', 'master');

        expect(ownerNames(User::query()->whereSkill('PHP', atLeast: 'expert')))->toBe(['Alice', 'Frank']);
    });
});

describe('ambiguous and unknown terms', function (): void {
    beforeEach(function (): void {
        Skill::query()->create(['name' => 'JSON Schema'])->addAlias('JS');
        user('Grace')->addSkill('json-schema');
    });

    it('matches every skill an ambiguous alias names instead of picking one', function (): void {
        expect(UserProfile::resolveSkills('JS')->pluck('name')->all())->toBe(['JavaScript', 'JSON Schema'])
            ->and(ownerNames(User::query()->whereSkill('JS')))->toBe(['Alice', 'Carol', 'Grace']);
    });

    it('refuses to write a skill through an ambiguous alias', function (): void {
        try {
            user('Heidi')->addSkill('js');
            $this->fail('An ambiguous alias must not be resolved silently.');
        } catch (AmbiguousProfileEntry $exception) {
            expect($exception->term)->toBe('js')
                ->and($exception->candidates->pluck('name')->all())->toBe(['JavaScript', 'JSON Schema']);
        }
    });

    it('still resolves unambiguous terms for writes', function (): void {
        expect(user('Heidi')->addSkill('ECMAScript')->skill_id)->toBe(Skill::query()->where('name', 'JavaScript')->value('id'));
    });

    it('returns no owners for unknown skills and languages', function (): void {
        expect(ownerNames(User::query()->whereSkill('COBOL')))->toBe([])
            ->and(ownerNames(User::query()->whereLanguage('Klingon')))->toBe([])
            ->and(ownerNames(User::query()->whereSkill(999_999)))->toBe([]);
    });

    it('excludes nobody for unknown exclusions', function (): void {
        expect(User::query()->withoutSkill('COBOL')->count())->toBe(User::query()->count())
            ->and(User::query()->withoutLanguage('Klingon')->count())->toBe(User::query()->count());
    });

    it('treats empty lists like whereIn: "any" matches nobody, "all" adds no condition', function (): void {
        expect(User::query()->whereAnySkill([])->count())->toBe(0)
            ->and(User::query()->whereAllSkills([])->count())->toBe(User::query()->count())
            ->and(User::query()->whereAnyLanguage([])->count())->toBe(0)
            ->and(User::query()->whereAllLanguages([])->count())->toBe(User::query()->count());
    });
});

describe('languages', function (): void {
    it('filters by language name, native name, code or ISO code', function (string $language): void {
        expect(ownerNames(User::query()->whereLanguage($language)))->toBe(['Alice'])
            ->and(ownerNames(User::query()->whereLanguage('English')))->toBe(['Alice', 'Bob', 'Carol']);
    })->with(['Arabic', 'arabic', 'العربية', 'ar', 'ara']);

    it('filters by minimum proficiency, counting native speakers', function (): void {
        expect(ownerNames(User::query()->whereLanguage('English', atLeast: 'advanced')))->toBe(['Alice', 'Bob'])
            ->and(ownerNames(User::query()->whereLanguage('English', atLeast: 'elementary')))->toBe(['Alice', 'Bob', 'Carol'])
            ->and(ownerNames(User::query()->whereLanguage('English', atLeast: 'proficient')))->toBe(['Alice']);
    });

    it('filters by native and non-native speakers', function (): void {
        expect(ownerNames(User::query()->whereLanguage('English', native: true)))->toBe(['Alice'])
            ->and(ownerNames(User::query()->whereLanguage('English', native: false)))->toBe(['Bob', 'Carol'])
            ->and(ownerNames(User::query()->whereLanguage('English', atLeast: 'advanced', native: false)))->toBe(['Bob']);
    });

    it('filters by primary language', function (): void {
        expect(ownerNames(User::query()->whereLanguage('English', primary: true)))->toBe(['Alice'])
            ->and(ownerNames(User::query()->whereLanguage('French', primary: true)))->toBe(['Carol']);
    });

    it('filters by any, all and excluded languages', function (): void {
        expect(ownerNames(User::query()->whereAnyLanguage(['Arabic', 'French'])))->toBe(['Alice', 'Carol'])
            ->and(ownerNames(User::query()->whereAllLanguages(['English', 'French'])))->toBe(['Carol'])
            ->and(ownerNames(User::query()->whereAllLanguages(['English', 'Arabic'], atLeast: 'intermediate')))->toBe(['Alice'])
            ->and(ownerNames(User::query()->withoutLanguage('English')))->toBe(['Dave']);
    });
});

describe('combined filters', function (): void {
    it('combines chained filters with AND', function (): void {
        expect(ownerNames(User::query()->whereLanguage('English')->whereSkill('PHP')))->toBe(['Alice', 'Bob'])
            ->and(ownerNames(User::query()->whereLanguage('English')->whereSkill('PHP')->whereSkill('Laravel')))->toBe(['Alice'])
            ->and(ownerNames(User::query()->whereLanguage('English', atLeast: 'intermediate')->whereSkill('PHP')))->toBe(['Alice', 'Bob']);
    });

    it('expresses English AND (PHP OR JavaScript) with an "any" filter', function (): void {
        user('Ivan')->addSkill('php');

        expect(ownerNames(User::query()->whereLanguage('English')->whereAnySkill(['PHP', 'JavaScript'])))->toBe(['Alice', 'Bob', 'Carol']);
    });

    it('supports grouped OR conditions with native where closures', function (): void {
        // French speakers OR (English speakers with PHP at advanced or above)
        $query = User::query()->where(fn ($query) => $query
            ->whereLanguage('French')
            ->orWhere(fn ($query) => $query->whereLanguage('English')->whereSkill('PHP', atLeast: 'advanced')));

        expect(ownerNames($query))->toBe(['Alice', 'Carol']);
    });

    it('never duplicates owners with several matching rows', function (): void {
        $results = User::query()
            ->whereAnySkill(['PHP', 'Laravel', 'JavaScript', 'WordPress'])
            ->whereAnyLanguage(['English', 'Arabic', 'French'])
            ->get();

        expect($results->pluck('name')->sort()->values()->all())->toBe(['Alice', 'Bob', 'Carol'])
            ->and($results->modelKeys())->toHaveCount(3);
    });

    it('returns owner models and paginates in the database', function (): void {
        foreach (range(1, 30) as $number) {
            user(sprintf('Paginated %02d', $number))->addSkill('laravel', 'advanced');
        }

        $page = User::query()->whereSkill('Laravel', atLeast: 'advanced')->orderBy('name')->paginate(10, page: 2);

        expect($page->total())->toBe(31)
            ->and($page->items())->each->toBeInstanceOf(User::class)
            ->and($page->pluck('name')->first())->toBe('Paginated 10')
            ->and(User::query()->whereSkill('Laravel')->cursorPaginate(5)->count())->toBe(5);
    });

    it('returns an empty result when nothing matches', function (): void {
        expect(User::query()->whereSkill('Laravel')->whereLanguage('French')->get())->toBeEmpty()
            ->and(User::query()->whereSkill('Laravel')->whereLanguage('French')->paginate(10)->total())->toBe(0);
    });
});

it('requires the feature to be enabled', function (): void {
    config()->set('user-profile.features.skills', false);

    User::query()->whereSkill('PHP');
})->throws(FeatureDisabled::class);
