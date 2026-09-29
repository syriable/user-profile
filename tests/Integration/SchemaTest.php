<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Syriable\UserProfile\Models\Skill;

/**
 * @return list<list<string>>
 */
function indexColumns(string $table, bool $unique = false): array
{
    return collect(Schema::getIndexes($table))
        ->filter(fn (array $index): bool => ! $unique || $index['unique'] || $index['primary'])
        ->map(fn (array $index): array => $index['columns'])
        ->values()
        ->all();
}

it('uses owner type, owner id and catalog id as the uniqueness boundary', function (string $table, string $column): void {
    expect(indexColumns($table, unique: true))->toContain(['profileable_type', 'profileable_id', $column])
        ->and(Schema::hasColumn($table, 'user_id'))->toBeFalse();
})->with([
    ['profile_skills', 'skill_id'],
    ['profile_languages', 'language_id'],
]);

it('indexes pivots for catalog-driven owner lookups', function (string $table, string $column): void {
    expect(indexColumns($table))->toContain([$column, 'profileable_type', 'profileable_id']);
})->with([
    ['profile_skills', 'skill_id'],
    ['profile_languages', 'language_id'],
]);

it('indexes owned records by owner type and id', function (string $table): void {
    expect(indexColumns($table))->toContain(['profileable_type', 'profileable_id']);
})->with(['educations', 'certifications', 'awards']);

it('lets different owner types hold the same skill while the same owner cannot hold it twice', function (): void {
    $skill = Skill::query()->create(['name' => 'PHP']);
    $user = user();
    $seller = seller();

    $user->skills()->attach($skill);
    $seller->skills()->attach($skill);

    expect(DB::table('profile_skills')->count())->toBe(2)
        ->and($user->id)->toBe($seller->id)
        ->and(fn () => $user->skills()->attach($skill))->toThrow(QueryException::class);
});
