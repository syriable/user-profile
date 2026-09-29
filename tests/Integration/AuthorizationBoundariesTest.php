<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Syriable\UserProfile\Exceptions\ProfileEntryNotFound;
use Syriable\UserProfile\Models\Award;
use Syriable\UserProfile\Models\Certification;
use Syriable\UserProfile\Models\Education;
use Syriable\UserProfile\Models\Skill;

it('never mass assigns the owner', function (string $model, array $attributes): void {
    $owner = user('Owner');
    $victim = user('Victim');

    $record = $owner->{$model}()->create($attributes + ['user_id' => $victim->id]);

    expect($record->user_id)->toBe($owner->id);
})->with([
    'education' => ['educations', ['institution_name' => 'University']],
    'certification' => ['certifications', ['name' => 'Cert', 'issuing_organization' => 'Org']],
    'award' => ['awards', ['title' => 'Award']],
]);

it('cannot create an owner-less record through mass assignment', function (string $model, array $attributes): void {
    $model::query()->create($attributes + ['user_id' => user()->id]);
})->with([
    [Education::class, ['institution_name' => 'University']],
    [Certification::class, ['name' => 'Cert', 'issuing_organization' => 'Org']],
    [Award::class, ['title' => 'Award']],
])->throws(QueryException::class);

it('scopes relationship lookups to the owner', function (): void {
    $owner = user('Owner');
    $other = user('Other');
    $education = $other->educations()->create(['institution_name' => 'University']);

    expect($owner->educations()->find($education->id))->toBeNull()
        ->and($education->isOwnedBy($other))->toBeTrue()
        ->and($education->isOwnedBy($owner))->toBeFalse();
});

it('cannot update or remove another user\'s pivot entries', function (): void {
    $owner = user('Owner');
    $other = user('Other');
    $skill = Skill::query()->create(['name' => 'PHP']);
    $other->addSkill($skill, 'beginner');

    expect(fn () => $owner->updateSkill($skill, ['proficiency_level' => 'expert']))->toThrow(ProfileEntryNotFound::class)
        ->and($owner->removeSkill($skill))->toBeFalse()
        ->and($other->skills()->first()?->pivot->proficiency_level)->toBe('beginner');
});

it('only clears primary flags on the acting user\'s profile', function (): void {
    $owner = user('Owner');
    $other = user('Other');
    $php = Skill::query()->create(['name' => 'PHP']);
    $go = Skill::query()->create(['name' => 'Go']);

    $other->addSkill($php, isPrimary: true);
    $owner->addSkill($go, isPrimary: true);

    expect($other->skills()->first()?->pivot->is_primary)->toBeTrue();
});
