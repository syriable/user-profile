<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
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
