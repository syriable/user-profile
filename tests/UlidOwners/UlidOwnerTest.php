<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Syriable\UserProfile\Exceptions\IncompatibleProfileOwner;
use Syriable\UserProfile\Models\Award;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Tests\Fixtures\Member;
use Syriable\UserProfile\Tests\Fixtures\Organization;

it('creates a ulid owner column', function (): void {
    expect(Schema::getColumnType('awards', 'profileable_id'))->toBeIn(['char', 'bpchar', 'varchar']);
});

it('manages and filters the profiles of ulid-keyed owners', function (): void {
    $acme = Organization::query()->create(['name' => 'Acme']);
    $globex = Organization::query()->create(['name' => 'Globex']);
    Skill::query()->create(['name' => 'Logistics']);
    Language::query()->create(['name' => 'English', 'code' => 'en']);

    $acme->addSkill('logistics', 'expert');
    $acme->addLanguage('en', 'advanced');
    $globex->addLanguage('en', 'beginner');
    $award = $acme->awards()->create(['title' => 'Best Supplier']);

    expect($acme->hasSkill('Logistics'))->toBeTrue()
        ->and($award->profileable?->is($acme))->toBeTrue()
        ->and(Organization::query()->whereLanguage('English', atLeast: 'advanced')->whereSkill('logistics')->pluck('name')->all())->toBe(['Acme'])
        ->and(Organization::query()->whereLanguage('en')->orderBy('name')->pluck('name')->all())->toBe(['Acme', 'Globex']);

    $acme->delete();

    expect(Award::query()->count())->toBe(0)
        ->and($globex->hasLanguage('en'))->toBeTrue();
});

it('rejects uuid-keyed owners whose keys are not ulids', function (): void {
    $member = new Member(['name' => 'Omar']);
    $member->uuid = (string) Str::uuid();

    $member->skills();
})->throws(IncompatibleProfileOwner::class, 'is not a valid [ulid] value');
