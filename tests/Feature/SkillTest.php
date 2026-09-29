<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Syriable\UserProfile\Exceptions\DuplicateProfileEntry;
use Syriable\UserProfile\Exceptions\InvalidProficiency;
use Syriable\UserProfile\Exceptions\ProfileEntryNotFound;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Models\ProfileSkill;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillCategory;

describe('catalog', function (): void {
    it('creates canonical skills with a slug and normalized name', function (): void {
        $skill = Skill::query()->create(['name' => ' Project   Management ']);

        expect($skill->fresh())
            ->name->toBe('Project   Management')
            ->normalized_name->toBe('project management')
            ->slug->toBe('project-management')
            ->is_active->toBeTrue()
            ->category_id->toBeNull();
    });

    it('keeps symbols that distinguish technology names in slugs', function (): void {
        $slugs = collect(['C', 'C++', 'C#'])
            ->map(fn (string $name) => Skill::query()->create(['name' => $name])->slug)
            ->all();

        expect($slugs)->toBe(['c', 'c-plus-plus', 'c-sharp']);
    });

    it('generates unique slugs when names collapse to the same slug', function (): void {
        Skill::query()->create(['name' => 'Node JS']);
        $second = Skill::query()->create(['name' => 'Node-JS']);

        expect($second->slug)->toBe('node-js-2');
    });

    it('prevents duplicate canonical skills regardless of case and spacing', function (): void {
        Skill::query()->create(['name' => 'JavaScript']);

        Skill::query()->create(['name' => ' javascript ']);
    })->throws(ValidationException::class, 'normalized name has already been taken');

    it('supports optional categories', function (): void {
        $category = SkillCategory::query()->create(['name' => 'Programming Languages']);
        $skill = Skill::query()->create(['name' => 'PHP', 'category_id' => $category->id]);

        expect($skill->category?->is($category))->toBeTrue()
            ->and($category->slug)->toBe('programming-languages')
            ->and($category->skills()->pluck('name')->all())->toBe(['PHP']);
    });

    it('keeps skills when their category is deleted', function (): void {
        $category = SkillCategory::query()->create(['name' => 'Design']);
        $skill = Skill::query()->create(['name' => 'Graphic Design', 'category_id' => $category->id]);

        $category->delete();

        expect($skill->fresh()?->category_id)->toBeNull();
    });
});

describe('user skills', function (): void {
    it('attaches skills with proficiency, experience and primary flag', function (): void {
        $user = user();
        $skill = Skill::query()->create(['name' => 'Laravel']);

        $pivot = $user->addSkill($skill, proficiency: 'expert', yearsOfExperience: 8, isPrimary: true);

        expect($pivot)->toBeInstanceOf(ProfileSkill::class)
            ->and($pivot->proficiency_level)->toBe('expert')
            ->and($pivot->proficiencyLabel())->toBe('Expert')
            ->and($pivot->years_of_experience)->toBe(8)
            ->and($pivot->is_primary)->toBeTrue();
    });

    it('allows skills without proficiency or experience', function (): void {
        $pivot = user()->addSkill(Skill::query()->create(['name' => 'Git']));

        expect($pivot->proficiency_level)->toBeNull()
            ->and($pivot->years_of_experience)->toBeNull();
    });

    it('does not infer proficiency from experience', function (): void {
        $pivot = user()->addSkill(Skill::query()->create(['name' => 'Git']), yearsOfExperience: 20);

        expect($pivot->proficiency_level)->toBeNull();
    });

    it('resolves skills by model, id or slug', function (): void {
        $user = user();
        $skill = Skill::query()->create(['name' => 'C++']);

        $user->addSkill('c-plus-plus');

        expect($user->hasSkill($skill))->toBeTrue()
            ->and($user->hasSkill($skill->id))->toBeTrue()
            ->and($user->hasSkill('c-plus-plus'))->toBeTrue()
            ->and($user->hasSkill('unknown'))->toBeFalse();
    });

    it('updates proficiency and experience', function (): void {
        $user = user();
        $skill = Skill::query()->create(['name' => 'PHP']);
        $user->addSkill($skill, 'intermediate', 2);

        $pivot = $user->updateSkill($skill, ['proficiency_level' => 'advanced', 'years_of_experience' => 4]);

        expect($pivot->proficiency_level)->toBe('advanced')
            ->and($pivot->years_of_experience)->toBe(4);
    });

    it('validates proficiency and experience', function (Closure $add, string $exception): void {
        $user = user();
        $skill = Skill::query()->create(['name' => 'PHP']);

        expect(fn () => $add($user, $skill))->toThrow($exception);
    })->with([
        'unknown level' => [fn ($user, $skill) => $user->addSkill($skill, 'guru'), InvalidProficiency::class],
        'language level on a skill' => [fn ($user, $skill) => $user->addSkill($skill, 'upper_intermediate'), InvalidProficiency::class],
        'negative years' => [fn ($user, $skill) => $user->addSkill($skill, yearsOfExperience: -1), InvalidArgumentException::class],
        'too many years' => [fn ($user, $skill) => $user->addSkill($skill, yearsOfExperience: 101), InvalidArgumentException::class],
    ]);

    it('uses application-defined skill levels', function (): void {
        config()->set('user-profile.skills.proficiency_levels', ['novice', 'practitioner', 'master']);

        $pivot = user()->addSkill(Skill::query()->create(['name' => 'Pottery']), 'master');

        expect($pivot->proficiency_level)->toBe('master')
            ->and($pivot->proficiencyLabel())->toBe('Master');
    });

    it('prevents duplicate user-skill associations', function (): void {
        $user = user();
        $skill = Skill::query()->create(['name' => 'PHP']);
        $user->addSkill($skill);

        $user->addSkill($skill->slug, 'expert');
    })->throws(DuplicateProfileEntry::class);

    it('does not leave partial writes when adding a duplicate primary skill', function (): void {
        $user = user();
        $php = Skill::query()->create(['name' => 'PHP']);
        $user->addSkill($php, isPrimary: true);

        try {
            $user->addSkill($php, isPrimary: true);
        } catch (DuplicateProfileEntry) {
        }

        expect($user->skills()->first()?->pivot->is_primary)->toBeTrue();
    });

    it('keeps a single primary skill per user', function (): void {
        $user = user();
        $user->addSkill(Skill::query()->create(['name' => 'PHP']), isPrimary: true);
        $user->addSkill(Skill::query()->create(['name' => 'Go']), isPrimary: true);

        expect($user->skills()->wherePivot('is_primary', true)->pluck('name')->all())->toBe(['Go']);
    });

    it('lets applications add custom skills', function (): void {
        $user = user();
        $skill = Skill::query()->create(['name' => 'Underwater Welding', 'description' => 'Hyperbaric welding.']);

        $user->addSkill($skill, 'advanced');

        expect($user->skills()->pluck('name')->all())->toBe(['Underwater Welding']);
    });

    it('removes a skill without deleting the catalog record', function (): void {
        $user = user();
        $skill = Skill::query()->create(['name' => 'PHP']);
        $user->addSkill($skill);

        expect($user->removeSkill($skill))->toBeTrue()
            ->and($user->hasSkill($skill))->toBeFalse()
            ->and($skill->exists)->toBeTrue();
    });

    it('throws when updating a skill that is not on the profile', function (): void {
        user()->updateSkill(Skill::query()->create(['name' => 'PHP']), ['is_primary' => true]);
    })->throws(ProfileEntryNotFound::class);

    it('finds users by skill and minimum proficiency', function (): void {
        $skill = Skill::query()->create(['name' => 'PHP']);
        user('Expert')->addSkill($skill, 'expert');
        user('Beginner')->addSkill($skill, 'beginner');
        user('Unrated')->addSkill($skill);
        user('None');

        $names = fn (?string $level) => user('Query')->newQuery()->whereSkill('php', $level)->orderBy('name')->pluck('name')->all();

        expect($names(null))->toBe(['Beginner', 'Expert', 'Unrated'])
            ->and($names('advanced'))->toBe(['Expert']);
    });

    it('sorts a profile by proficiency rank', function (): void {
        $user = user();
        $user->addSkill(Skill::query()->create(['name' => 'A']), 'beginner');
        $user->addSkill(Skill::query()->create(['name' => 'B']), 'expert');
        $user->addSkill(Skill::query()->create(['name' => 'C']), 'intermediate');

        $scale = UserProfile::skillProficiency();

        $sorted = $user->skills()->get()
            ->sortByDesc(fn (Skill $skill) => $scale->rank((string) $skill->pivot->proficiency_level))
            ->pluck('name')
            ->values()
            ->all();

        expect($sorted)->toBe(['B', 'C', 'A']);
    });
});

it('rejects pivot attributes of the wrong type instead of silently dropping them', function (array $attributes): void {
    $user = user();
    $skill = Skill::query()->create(['name' => 'PHP']);
    $user->addSkill($skill, 'beginner', 1);

    $user->updateSkill($skill, $attributes);
})->with([
    'numeric string years' => [['years_of_experience' => '5']],
    'integer proficiency' => [['proficiency_level' => 3]],
    'truthy primary flag' => [['is_primary' => 1]],
])->throws(InvalidArgumentException::class);
