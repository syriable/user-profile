<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Tests\Fixtures\User;

/**
 * Runs the callback and returns the SQL statements it executed.
 *
 * @return list<string>
 */
function executedSql(Closure $callback): array
{
    $statements = [];

    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $callback();

    return $statements;
}

beforeEach(function (): void {
    $php = Skill::query()->create(['name' => 'PHP']);
    Skill::query()->create(['name' => 'Laravel']);
    Language::query()->create(['name' => 'English', 'code' => 'en']);

    foreach (range(1, 60) as $number) {
        $owner = user("Owner {$number}");

        if ($number % 10 === 0) {
            $owner->addSkill($php, 'expert');
            $owner->addLanguage('en', 'advanced');
        }
    }
});

it('filters in one database query and only hydrates matching owners', function (): void {
    $hydrated = 0;
    User::retrieved(function () use (&$hydrated): void {
        $hydrated++;
    });

    $matches = null;
    $sql = executedSql(function () use (&$matches): void {
        $matches = User::query()
            ->whereSkill(Skill::query()->where('name', 'PHP')->firstOrFail(), atLeast: 'advanced')
            ->whereLanguage(Language::query()->where('code', 'en')->firstOrFail())
            ->get();
    });

    $ownerQueries = array_values(array_filter($sql, fn (string $statement): bool => str_contains($statement, 'from "users"')));

    expect($matches)->toHaveCount(6)
        ->and($hydrated)->toBe(6)
        ->and($ownerQueries)->toHaveCount(1)
        ->and($ownerQueries[0])
        ->toContain('exists (select * from "profile_skills"')
        ->toContain('exists (select * from "profile_languages"')
        ->toContain('"profile_skills"."profileable_type" = ?')
        ->toContain('"profile_languages"."profileable_type" = ?')
        ->not->toContain('join');
})->skip(fn (): bool => DB::getDriverName() !== 'sqlite', 'Asserts SQLite identifier quoting.');

it('resolves each term once and then filters by id', function (): void {
    $sql = executedSql(fn () => User::query()->whereSkill('php')->whereLanguage('English')->get());

    $catalogQueries = array_filter($sql, fn (string $statement): bool => ! str_contains($statement, 'from "users"'));

    expect($catalogQueries)->toHaveCount(2)
        ->and(end($sql))->toContain('"profile_skills"."skill_id" in (?)')
        ->and(end($sql))->not->toContain('like');
})->skip(fn (): bool => DB::getDriverName() !== 'sqlite', 'Asserts SQLite identifier quoting.');

it('paginates with count and limit queries in the database', function (): void {
    $page = null;
    $sql = executedSql(function () use (&$page): void {
        $page = User::query()->whereSkill(1)->orderBy('id')->paginate(4, page: 2);
    });

    expect($page->total())->toBe(6)
        ->and($page->count())->toBe(2)
        ->and($sql)->toHaveCount(2)
        ->and($sql[0])->toContain('select count(*) as')
        ->and($sql[1])->toContain('limit 4 offset 4');
});

it('eager loads profiles without N+1 queries', function (): void {
    $sql = executedSql(fn () => User::query()->with(['skills', 'languages', 'educations'])->get());

    expect($sql)->toHaveCount(4);
});
