<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Syriable\UserProfile\Events\LanguageAdded;
use Syriable\UserProfile\Events\LanguageRemoved;
use Syriable\UserProfile\Events\LanguageUpdated;
use Syriable\UserProfile\Events\SkillAdded;
use Syriable\UserProfile\Events\SkillRemoved;
use Syriable\UserProfile\Events\SkillUpdated;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;

it('dispatches skill events', function (): void {
    Event::fake();
    $user = user();
    $skill = Skill::query()->create(['name' => 'PHP']);

    $user->addSkill($skill, 'advanced');
    $user->updateSkill($skill, ['proficiency_level' => 'expert']);
    $user->removeSkill($skill);
    $user->removeSkill($skill);

    Event::assertDispatched(SkillAdded::class, fn (SkillAdded $event) => $event->owner->is($user) && $event->skill->is($skill) && $event->pivot->proficiency_level === 'advanced');
    Event::assertDispatched(SkillUpdated::class, fn (SkillUpdated $event) => $event->pivot->proficiency_level === 'expert');
    Event::assertDispatchedTimes(SkillRemoved::class, 1);
});

it('dispatches language events', function (): void {
    Event::fake();
    $user = user();
    $language = Language::query()->create(['name' => 'Arabic', 'code' => 'ar']);

    $user->addLanguage($language, isNative: true);
    $user->updateLanguage($language, ['is_primary' => true]);
    $user->removeLanguage($language);

    Event::assertDispatched(LanguageAdded::class, fn (LanguageAdded $event) => $event->pivot->is_native);
    Event::assertDispatched(LanguageUpdated::class, fn (LanguageUpdated $event) => $event->pivot->is_primary);
    Event::assertDispatched(LanguageRemoved::class);
});

it('still derives attributes and validates when model events are faked', function (): void {
    Event::fake();

    $skill = Skill::query()->create(['name' => 'C#']);

    expect($skill->slug)->toBe('c-sharp')
        ->and(fn () => Skill::query()->create(['name' => 'c#']))->toThrow(ValidationException::class);
});
