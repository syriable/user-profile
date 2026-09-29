<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Syriable\UserProfile\Exceptions\IncompatibleProfileOwner;
use Syriable\UserProfile\Models\Education;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Tests\Fixtures\Member;
use Syriable\UserProfile\Tests\Fixtures\User;

it('creates a uuid owner column', function (): void {
    expect(Schema::getColumnType('profile_skills', 'profileable_id'))->toBeIn(['varchar', 'uuid', 'char', 'bpchar'])
        ->and(Schema::hasColumn('profile_skills', 'user_id'))->toBeFalse();
});

it('manages the full profile of a uuid-keyed owner with a custom key name', function (): void {
    $member = Member::query()->create(['name' => 'Omar']);
    $php = Skill::query()->create(['name' => 'PHP']);
    Language::query()->create(['name' => 'Arabic', 'code' => 'ar']);

    $member->addSkill($php, 'expert');
    $member->addLanguage('ar', isNative: true);
    $education = $member->educations()->create(['institution_name' => 'Aleppo University']);
    $member->certifications()->create(['name' => 'Cert', 'issuing_organization' => 'Org']);
    $member->awards()->create(['title' => 'Award']);

    expect($member->skills()->first()?->pivot->profileable_id)->toBe($member->uuid)
        ->and($member->hasLanguage('ar'))->toBeTrue()
        ->and($education->profileable?->is($member))->toBeTrue()
        ->and($education->isOwnedBy($member))->toBeTrue()
        ->and($php->profileSkills()->first()?->profileable?->is($member))->toBeTrue();
});

it('filters uuid-keyed owners in the database', function (): void {
    $php = Skill::query()->create(['name' => 'PHP']);
    Member::query()->create(['name' => 'Omar'])->addSkill($php, 'expert');
    Member::query()->create(['name' => 'Lina'])->addSkill($php, 'beginner');
    Member::query()->create(['name' => 'Sami']);

    expect(Member::query()->whereSkill('php', atLeast: 'advanced')->pluck('name')->all())->toBe(['Omar'])
        ->and(Member::query()->withoutSkill('php')->pluck('name')->all())->toBe(['Sami'])
        ->and(Member::query()->whereSkill('php')->paginate(1)->total())->toBe(2);
});

it('removes the profile of a deleted uuid-keyed owner', function (): void {
    $member = Member::query()->create(['name' => 'Omar']);
    $member->addSkill(Skill::query()->create(['name' => 'PHP']));
    $member->educations()->create(['institution_name' => 'Aleppo University']);

    $member->delete();

    expect(Education::query()->count())->toBe(0)
        ->and(DB::table('profile_skills')->count())->toBe(0)
        ->and(Skill::query()->count())->toBe(1);
});

it('rejects integer-keyed owners when the owner key type is uuid', function (): void {
    User::query()->create(['name' => 'Jane'])->skills()->get();
})->throws(IncompatibleProfileOwner::class, 'uses [int] primary keys, but user-profile.owner_key_type is [uuid]');

it('rejects owner keys that are not uuids', function (): void {
    $member = new Member(['name' => 'Broken']);
    $member->uuid = 'not-a-uuid';

    $member->skills();
})->throws(IncompatibleProfileOwner::class, 'is not a valid [uuid] value');
