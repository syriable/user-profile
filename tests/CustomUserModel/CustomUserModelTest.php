<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Syriable\UserProfile\Models\Education;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Tests\Fixtures\Member;

it('creates uuid foreign keys pointing at the configured user table', function (): void {
    expect(Schema::getColumnType('user_skills', 'user_id'))->toBeIn(['varchar', 'uuid', 'char'])
        ->and(collect(Schema::getForeignKeys('user_skills'))->firstWhere('foreign_table', 'members')['foreign_columns'] ?? null)->toBe(['uuid']);
});

it('manages the full profile of a uuid-keyed user', function (): void {
    $member = Member::query()->create(['name' => 'Omar']);
    $php = Skill::query()->create(['name' => 'PHP']);
    Language::query()->create(['name' => 'Arabic', 'code' => 'ar']);

    $member->addSkill($php, 'expert');
    $member->addLanguage('ar', isNative: true);
    $education = $member->educations()->create(['institution_name' => 'Aleppo University']);
    $member->certifications()->create(['name' => 'Cert', 'issuing_organization' => 'Org']);
    $member->awards()->create(['title' => 'Award']);

    expect($member->skills()->first()?->pivot->user_id)->toBe($member->uuid)
        ->and($member->hasLanguage('ar'))->toBeTrue()
        ->and($education->user?->is($member))->toBeTrue()
        ->and($education->isOwnedBy($member))->toBeTrue()
        ->and(Member::query()->whereHasSkill($php, 'advanced')->pluck('name')->all())->toBe(['Omar'])
        ->and($php->users()->first()?->is($member))->toBeTrue();
});

it('cascades profile records when the user is deleted', function (): void {
    $member = Member::query()->create(['name' => 'Omar']);
    $member->addSkill(Skill::query()->create(['name' => 'PHP']));
    $member->educations()->create(['institution_name' => 'Aleppo University']);

    $member->delete();

    expect(Education::query()->count())->toBe(0)
        ->and(DB::table('user_skills')->count())->toBe(0)
        ->and(Skill::query()->count())->toBe(1);
});
