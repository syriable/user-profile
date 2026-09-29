<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Syriable\UserProfile\Models\Award;
use Syriable\UserProfile\Models\Certification;
use Syriable\UserProfile\Models\Education;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Tests\Fixtures\Seller;
use Syriable\UserProfile\Tests\Fixtures\SoftDeletingUser;
use Syriable\UserProfile\Tests\Fixtures\User;

beforeEach(function (): void {
    Skill::query()->create(['name' => 'PHP']);
    Skill::query()->create(['name' => 'Laravel']);
    Language::query()->create(['name' => 'English', 'code' => 'en']);
});

it('gives two owner types with the same id separate profiles', function (): void {
    $user = user('Jane');
    $seller = seller('Acme');

    expect($user->id)->toBe($seller->id);

    $user->addSkill('php', 'expert');
    $user->addLanguage('en', isNative: true);
    $user->educations()->create(['institution_name' => 'University']);
    $seller->addSkill('laravel', 'beginner');
    $seller->awards()->create(['title' => 'Top Seller']);

    expect($user->skills->pluck('name')->all())->toBe(['PHP'])
        ->and($seller->skills->pluck('name')->all())->toBe(['Laravel'])
        ->and($user->hasSkill('laravel'))->toBeFalse()
        ->and($seller->hasSkill('php'))->toBeFalse()
        ->and($seller->languages)->toBeEmpty()
        ->and($seller->educations)->toBeEmpty()
        ->and($user->awards)->toBeEmpty()
        ->and(DB::table('profile_skills')->pluck('profileable_type')->sort()->values()->all())->toBe([Seller::class, User::class]);
});

it('never matches the other owner type when filtering', function (): void {
    user('Jane')->addSkill('php');
    seller('Acme')->addSkill('laravel');

    expect(User::query()->whereSkill('php')->pluck('name')->all())->toBe(['Jane'])
        ->and(Seller::query()->whereSkill('php')->pluck('name')->all())->toBe([])
        ->and(Seller::query()->whereSkill('laravel')->pluck('name')->all())->toBe(['Acme'])
        ->and(User::query()->withoutSkill('laravel')->pluck('name')->all())->toBe(['Jane']);
});

it('only updates and removes entries of the acting owner type', function (): void {
    $user = user('Jane');
    $seller = seller('Acme');
    $user->addSkill('php', 'beginner', isPrimary: true);
    $seller->addSkill('php', 'expert', isPrimary: true);

    $user->updateSkill('php', ['proficiency_level' => 'advanced']);
    $user->addSkill('laravel', isPrimary: true);
    $user->removeSkill('php');

    expect($seller->skills()->first()?->pivot->proficiency_level)->toBe('expert')
        ->and($seller->skills()->first()?->pivot->is_primary)->toBeTrue()
        ->and($user->skills->pluck('name')->all())->toBe(['Laravel']);
});

it('resolves the owner of pivot rows and records', function (): void {
    $seller = seller('Acme');
    $seller->addSkill('php');
    $education = $seller->educations()->create(['institution_name' => 'Trade School']);

    $entry = Skill::query()->where('name', 'PHP')->firstOrFail()->profileSkills()->with('profileable')->firstOrFail();

    expect($entry->profileable)->toBeInstanceOf(Seller::class)
        ->and($entry->profileable?->is($seller))->toBeTrue()
        ->and($education->profileable)->toBeInstanceOf(Seller::class)
        ->and($education->isOwnedBy(user('Jane')))->toBeFalse();
});

it('respects the application morph map without requiring one', function (): void {
    Relation::morphMap(['seller' => Seller::class]);

    try {
        $seller = seller('Acme');
        $seller->addSkill('php');
        $seller->certifications()->create(['name' => 'Cert', 'issuing_organization' => 'Org']);

        expect(DB::table('profile_skills')->value('profileable_type'))->toBe('seller')
            ->and(Certification::query()->value('profileable_type'))->toBe('seller')
            ->and(Seller::query()->whereSkill('php')->pluck('name')->all())->toBe(['Acme'])
            ->and(Certification::query()->firstOrFail()->profileable)->toBeInstanceOf(Seller::class);
    } finally {
        Relation::morphMap([], false);
    }
});

describe('deleting owners', function (): void {
    it('deletes the whole profile of a deleted owner and nothing else', function (): void {
        $user = user('Jane');
        $seller = seller('Acme');

        foreach ([$user, $seller] as $owner) {
            $owner->addSkill('php');
            $owner->addLanguage('en');
            $owner->educations()->create(['institution_name' => 'University']);
            $owner->certifications()->create(['name' => 'Cert', 'issuing_organization' => 'Org']);
            $owner->awards()->create(['title' => 'Award']);
        }

        $user->delete();

        expect(DB::table('profile_skills')->pluck('profileable_type')->all())->toBe([Seller::class])
            ->and(DB::table('profile_languages')->pluck('profileable_type')->all())->toBe([Seller::class])
            ->and(Education::query()->pluck('profileable_type')->all())->toBe([Seller::class])
            ->and(Certification::query()->pluck('profileable_type')->all())->toBe([Seller::class])
            ->and(Award::query()->pluck('profileable_type')->all())->toBe([Seller::class])
            ->and(Skill::query()->count())->toBe(2)
            ->and(Language::query()->count())->toBe(1);
    });

    it('keeps the profile of a soft-deleted owner until it is force deleted', function (): void {
        $owner = SoftDeletingUser::query()->create(['name' => 'Jane']);
        $owner->addSkill('php');

        $owner->delete();

        expect(DB::table('profile_skills')->count())->toBe(1);

        $owner->restore();

        expect($owner->hasSkill('php'))->toBeTrue();

        $owner->forceDelete();

        expect(DB::table('profile_skills')->count())->toBe(0);
    });

    it('leaves cleanup to deleteProfile() for query builder deletes, which fire no model events', function (): void {
        $user = user('Jane');
        $user->addSkill('php');

        User::query()->whereKey($user->id)->delete();

        expect(DB::table('profile_skills')->count())->toBe(1);

        $user->deleteProfile();

        expect(DB::table('profile_skills')->count())->toBe(0);
    });

    it('rolls back profile cleanup together with a failed owner deletion', function (): void {
        $user = user('Jane');
        $user->addSkill('php');

        try {
            DB::transaction(function () use ($user): void {
                $user->delete();

                throw new RuntimeException('Abort');
            });
        } catch (RuntimeException) {
        }

        expect(User::query()->count())->toBe(1)
            ->and(DB::table('profile_skills')->count())->toBe(1);
    });

    it('skips tables of disabled features', function (): void {
        remigrate(fn () => config()->set('user-profile.features.awards', false));
        Skill::query()->create(['name' => 'PHP']);

        $user = user('Jane');
        $user->addSkill('php');

        $user->delete();

        expect(DB::table('profile_skills')->count())->toBe(0);
    });

    it('does not clean up when model events are faked, as with any Eloquent listener', function (): void {
        $user = user('Jane');
        $user->addSkill('php');

        Event::fake();
        $user->delete();

        expect(DB::table('profile_skills')->count())->toBe(1);
    });
});

it('supports factories for polymorphic owners', function (): void {
    $seller = seller('Acme');

    $education = Education::factory()->for($seller, 'profileable')->current()->create();

    expect($education->isOwnedBy($seller))->toBeTrue()
        ->and($seller->educations()->count())->toBe(1);
});
