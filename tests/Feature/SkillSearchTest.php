<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Syriable\UserProfile\Contracts\SkillSearchResolver;
use Syriable\UserProfile\Database\Seeders\SkillSeeder;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillCategory;
use Syriable\UserProfile\Search\DatabaseSkillSearchResolver;
use Syriable\UserProfile\Search\SkillSearchCriteria;

/**
 * @return list<string>
 */
function skillNames(Builder $query): array
{
    return $query->pluck('name')->all();
}

beforeEach(function (): void {
    $this->seed(SkillSeeder::class);
});

it('matches canonical names exactly', function (): void {
    expect(skillNames(UserProfile::searchSkills('JavaScript', partial: false)))->toBe(['JavaScript']);
});

it('matches aliases exactly', function (): void {
    expect(skillNames(UserProfile::searchSkills('JS', partial: false)))->toBe(['JavaScript'])
        ->and(skillNames(UserProfile::searchSkills('SEO', partial: false)))->toBe(['Search Engine Optimization']);
});

it('is case and whitespace insensitive', function (string $term): void {
    expect(skillNames(UserProfile::searchSkills($term, partial: false)))->toBe(['Project Management']);
})->with(['project management', 'PROJECT MANAGEMENT', '  Project    Management  ', 'pm']);

it('matches partially on names and aliases', function (): void {
    expect(skillNames(UserProfile::searchSkills('script')))->toBe(['JavaScript', 'TypeScript'])
        ->and(skillNames(UserProfile::searchSkills('ecma')))->toBe(['JavaScript']);
});

it('ranks exact names, then exact aliases, then prefixes, then other matches', function (): void {
    Skill::query()->create(['name' => 'Advanced UI Animation']);
    Skill::query()->create(['name' => 'UI']);
    Skill::query()->create(['name' => 'UIKit']);

    expect(skillNames(UserProfile::searchSkills('ui')))->toBe([
        'UI',                    // exact canonical name
        'User Interface Design', // exact alias "UI"
        'UIKit',                 // canonical name starts with the term
        'Advanced UI Animation', // contains the term
    ]);
});

it('returns each canonical skill once even when several aliases match', function (): void {
    $results = UserProfile::searchSkills('design')->get();

    expect($results->pluck('name')->all())->toBe(['Graphic Design', 'User Experience Design', 'User Interface Design'])
        ->and($results->pluck('id')->duplicates())->toBeEmpty();
});

it('returns every skill sharing an ambiguous alias', function (): void {
    Skill::query()->create(['name' => 'JSON Schema'])->addAlias('JS');

    expect(skillNames(UserProfile::searchSkills('JS', partial: false)))->toBe(['JavaScript', 'JSON Schema']);
});

it('does not use partial matching for terms shorter than the configured minimum', function (): void {
    expect(skillNames(UserProfile::searchSkills('c')))->toBe(['C']);

    config()->set('user-profile.search.min_partial_length', 1);

    expect(UserProfile::searchSkills('c')->count())->toBeGreaterThan(1);
});

it('can disable partial matching globally', function (): void {
    config()->set('user-profile.search.partial_matching', false);

    expect(skillNames(UserProfile::searchSkills('script')))->toBe([]);
});

it('can disable alias matching', function (): void {
    config()->set('user-profile.search.aliases', false);

    expect(skillNames(UserProfile::searchSkills('JS', partial: false)))->toBe([])
        ->and(skillNames(UserProfile::searchSkills('JS')))->toBe(['Vue.js']);
});

it('treats LIKE wildcards in the term literally', function (): void {
    Skill::query()->create(['name' => '100% Uptime']);

    expect(skillNames(UserProfile::searchSkills('0%')))->toBe(['100% Uptime'])
        ->and(skillNames(UserProfile::searchSkills('__')))->toBe([])
        ->and(skillNames(UserProfile::searchSkills('!%')))->toBe([]);
});

it('filters by category', function (): void {
    $design = SkillCategory::query()->where('name', 'Design')->firstOrFail();

    expect(skillNames(UserProfile::searchSkills('ui', category: $design)))->toBe(['User Interface Design'])
        ->and(skillNames(UserProfile::searchSkills('ui', category: $design->id)))->toBe(['User Interface Design'])
        ->and(skillNames(UserProfile::searchSkills('javascript', category: $design)))->toBe([]);
});

it('excludes inactive skills unless requested', function (): void {
    Skill::query()->where('name', 'Java')->update(['is_active' => false]);

    expect(skillNames(UserProfile::searchSkills('java', partial: false)))->toBe([])
        ->and(skillNames(UserProfile::searchSkills('java', partial: false, includeInactive: true)))->toBe(['Java']);
});

it('returns no results for empty input', function (string $term): void {
    expect(UserProfile::searchSkills($term)->get())->toBeEmpty();
})->with(['', '   ', "\t\n"]);

it('paginates results deterministically', function (): void {
    foreach (range(1, 25) as $number) {
        Skill::query()->create(['name' => sprintf('Paginated Skill %02d', $number)]);
    }

    $first = UserProfile::searchSkills('paginated')->paginate(10);
    $third = UserProfile::searchSkills('paginated')->paginate(10, page: 3);

    expect($first->total())->toBe(25)
        ->and($first->pluck('name')->first())->toBe('Paginated Skill 01')
        ->and($third->pluck('name')->all())->toBe(['Paginated Skill 21', 'Paginated Skill 22', 'Paginated Skill 23', 'Paginated Skill 24', 'Paginated Skill 25']);
});

it('eager loads aliases when requested', function (): void {
    $skill = UserProfile::searchSkills('js', withAliases: true)->firstOrFail();

    expect($skill->relationLoaded('aliases'))->toBeTrue()
        ->and($skill->aliases->pluck('alias')->all())->toBe(['ECMAScript', 'JS']);
});

it('restricts alias matches to a locale', function (): void {
    $skill = Skill::query()->where('name', 'Search Engine Optimization')->firstOrFail();
    $skill->addAlias('Référencement naturel', 'fr');

    expect(skillNames(UserProfile::searchSkills('référencement', locale: 'fr')))->toBe(['Search Engine Optimization'])
        ->and(skillNames(UserProfile::searchSkills('référencement', locale: 'de')))->toBe([])
        ->and(skillNames(UserProfile::searchSkills('seo', locale: 'de')))->toBe(['Search Engine Optimization'])
        ->and(UserProfile::searchSkills('seo', locale: 'de', withAliases: true)->firstOrFail()->aliases->pluck('alias')->all())->toBe(['SEO']);
});

it('matches non-latin names and aliases', function (): void {
    Skill::query()->create(['name' => 'البرمجة'])->addAlias('برمجة', 'ar');

    expect(skillNames(UserProfile::searchSkills('برمجة')))->toBe(['البرمجة']);
});

it('allows applications to register a custom search resolver', function (): void {
    $resolver = new class extends DatabaseSkillSearchResolver
    {
        public ?SkillSearchCriteria $received = null;

        public function search(SkillSearchCriteria $criteria): Builder
        {
            $this->received = $criteria;

            return parent::search($criteria)->where('name', '!=', 'TypeScript');
        }
    };

    UserProfile::registerSkillSearchResolver($resolver);

    expect(skillNames(UserProfile::searchSkills('script')))->toBe(['JavaScript'])
        ->and($resolver->received?->term)->toBe('script')
        ->and(app(SkillSearchResolver::class))->toBe($resolver);
});

it('accepts a resolver class name and validates it', function (): void {
    UserProfile::registerSkillSearchResolver(DatabaseSkillSearchResolver::class);

    expect(app(SkillSearchResolver::class))->toBeInstanceOf(DatabaseSkillSearchResolver::class)
        ->and(fn () => UserProfile::registerSkillSearchResolver(stdClass::class))->toThrow(InvalidArgumentException::class);
});

it('supports the documented popularity resolver example', function (): void {
    $resolver = new class extends DatabaseSkillSearchResolver
    {
        public function search(SkillSearchCriteria $criteria): Builder
        {
            return parent::search($criteria)->reorder()
                ->withCount('profileSkills')
                ->orderByDesc('profile_skills_count')
                ->orderBy('name');
        }
    };

    UserProfile::registerSkillSearchResolver($resolver);
    user()->addSkill('typescript');

    expect(skillNames(UserProfile::searchSkills('script')))->toBe(['TypeScript', 'JavaScript'])
        ->and(UserProfile::searchSkills('script')->paginate(1)->total())->toBe(2);
});
