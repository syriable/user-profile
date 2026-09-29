<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillAlias;

it('adds multiple aliases to a canonical skill', function (): void {
    $skill = Skill::query()->create(['name' => 'JavaScript']);

    $skill->addAlias('JS');
    $skill->addAlias('ECMAScript');

    expect($skill->aliases()->orderBy('alias')->pluck('alias')->all())->toBe(['ECMAScript', 'JS']);
});

it('normalizes aliases for lookup while keeping the original spelling', function (): void {
    $alias = Skill::query()->create(['name' => 'User Interface Design'])->addAlias('  UI   Design ');

    expect($alias->alias)->toBe('UI   Design')
        ->and($alias->normalized_alias)->toBe('ui design');
});

it('does not duplicate an alias for the same skill and locale', function (): void {
    $skill = Skill::query()->create(['name' => 'JavaScript']);

    $first = $skill->addAlias('JS');
    $second = $skill->addAlias('js ');

    expect($second->is($first))->toBeTrue()
        ->and($skill->aliases()->count())->toBe(1);
});

it('validates alias uniqueness per skill and locale when created directly', function (): void {
    $skill = Skill::query()->create(['name' => 'JavaScript']);
    $skill->aliases()->create(['alias' => 'JS']);

    $skill->aliases()->create(['alias' => 'js']);
})->throws(ValidationException::class);

it('allows the same alias in different locales', function (): void {
    $skill = Skill::query()->create(['name' => 'Search Engine Optimization']);

    $skill->addAlias('SEO');
    $skill->addAlias('Référencement', 'fr');
    $skill->addAlias('SEO', 'fr');

    expect($skill->aliases()->count())->toBe(3);
});

it('allows the same alias on different skills', function (): void {
    Skill::query()->create(['name' => 'JavaScript'])->addAlias('JS');
    Skill::query()->create(['name' => 'JSON Schema'])->addAlias('JS');

    expect(SkillAlias::query()->where('normalized_alias', 'js')->count())->toBe(2);
});

it('validates alias locales', function (): void {
    Skill::query()->create(['name' => 'PHP'])->addAlias('P', 'not a locale');
})->throws(ValidationException::class);

it('removes aliases', function (): void {
    $skill = Skill::query()->create(['name' => 'JavaScript']);
    $skill->addAlias('JS');
    $skill->addAlias('JS', 'de');
    $skill->addAlias('ECMAScript');

    expect($skill->removeAlias('js', 'de'))->toBe(1)
        ->and($skill->removeAlias('JS'))->toBe(1)
        ->and($skill->aliases()->pluck('alias')->all())->toBe(['ECMAScript']);
});

it('deletes aliases together with their skill', function (): void {
    $skill = Skill::query()->create(['name' => 'JavaScript']);
    $skill->addAlias('JS');

    $skill->delete();

    expect(SkillAlias::query()->count())->toBe(0);
});

it('does not merge skills with similar names', function (): void {
    Skill::query()->create(['name' => 'Java']);
    Skill::query()->create(['name' => 'JavaScript']);

    expect(Skill::query()->count())->toBe(2);
});
