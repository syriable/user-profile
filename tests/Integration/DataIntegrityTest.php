<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Syriable\UserProfile\Models\Certification;
use Syriable\UserProfile\Models\Education;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;

it('refuses to delete a skill that is on a user profile', function (): void {
    $skill = Skill::query()->create(['name' => 'PHP']);
    user()->addSkill($skill, 'expert');

    $skill->delete();
})->throws(QueryException::class);

it('refuses to delete a language that is on a user profile', function (): void {
    $language = Language::query()->create(['name' => 'Arabic', 'code' => 'ar']);
    user()->addLanguage($language);

    $language->delete();
})->throws(QueryException::class);

it('allows retiring catalog records without touching profiles', function (): void {
    $skill = Skill::query()->create(['name' => 'Flash']);
    $user = user();
    $user->addSkill($skill, 'expert');

    $skill->update(['is_active' => false]);

    expect($user->skills()->first()?->pivot->proficiency_level)->toBe('expert');
});

it('rejects pivot rows for records that do not exist', function (): void {
    user()->skills()->attach(999);
})->throws(QueryException::class);

it('deletes a user\'s own records when the user is deleted', function (): void {
    $user = user();
    $user->addSkill(Skill::query()->create(['name' => 'PHP']));
    $user->addLanguage(Language::query()->create(['name' => 'Arabic', 'code' => 'ar']));
    $user->educations()->create(['institution_name' => 'University']);
    $user->certifications()->create(['name' => 'Cert', 'issuing_organization' => 'Org']);
    $user->awards()->create(['title' => 'Award']);

    $user->delete();

    expect(DB::table('user_skills')->count())->toBe(0)
        ->and(DB::table('user_languages')->count())->toBe(0)
        ->and(Education::query()->count())->toBe(0)
        ->and(Certification::query()->count())->toBe(0)
        ->and(DB::table('awards')->count())->toBe(0)
        ->and(Skill::query()->count())->toBe(1)
        ->and(Language::query()->count())->toBe(1);
});

it('rejects profile records for users that do not exist', function (): void {
    $education = new Education(['institution_name' => 'University']);
    $education->user_id = 999;
    $education->save();
})->throws(QueryException::class);
