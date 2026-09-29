<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Syriable\UserProfile\Enums\Feature;
use Syriable\UserProfile\Exceptions\FeatureDisabled;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\Skill;

it('publishes the configuration file', function (): void {
    $path = config_path('user-profile.php');
    File::delete($path);

    $this->artisan('vendor:publish', ['--tag' => 'user-profile-config'])->assertSuccessful();

    expect($path)->toBeFile()
        ->and(require $path)->toHaveKeys(['user', 'features', 'models', 'table_names', 'languages', 'skills', 'search', 'validation']);

    File::delete($path);
});

it('publishes the migration', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'user-profile-migrations'])->assertSuccessful();

    $published = File::glob(database_path('migrations/*_create_user_profile_tables.php'));

    expect($published)->toHaveCount(1);

    File::delete($published);
});

it('publishes the translations', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'user-profile-translations'])->assertSuccessful();

    expect(lang_path('vendor/user-profile/en/proficiency.php'))->toBeFile();

    File::deleteDirectory(lang_path('vendor/user-profile'));
});

it('registers the install command', function (): void {
    expect(array_keys(Artisan::all()))->toContain('user-profile:install');
});

it('creates every table when all features are enabled', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with(['languages', 'user_languages', 'skill_categories', 'skills', 'skill_aliases', 'user_skills', 'educations', 'certifications', 'awards']);

it('uses configured table names', function (): void {
    remigrate(function (): void {
        config()->set('user-profile.table_names.skills', 'catalog_skills');
        config()->set('user-profile.table_names.user_skills', 'member_skills');
    });

    $skill = Skill::query()->create(['name' => 'PHP']);
    user()->addSkill($skill);

    expect(Schema::hasTable('catalog_skills'))->toBeTrue()
        ->and(Schema::hasTable('skills'))->toBeFalse()
        ->and(DB::table('member_skills')->count())->toBe(1);
});

it('skips tables of disabled features and guards their helpers', function (): void {
    remigrate(function (): void {
        config()->set('user-profile.features.skills', false);
        config()->set('user-profile.features.awards', false);
    });

    expect(Schema::hasTable('skills'))->toBeFalse()
        ->and(Schema::hasTable('skill_aliases'))->toBeFalse()
        ->and(Schema::hasTable('awards'))->toBeFalse()
        ->and(Schema::hasTable('languages'))->toBeTrue()
        ->and(UserProfile::featureEnabled(Feature::Skills))->toBeFalse();

    Language::query()->create(['name' => 'Arabic', 'code' => 'ar']);
    user()->addLanguage('ar');

    user('Other')->addSkill(1);
})->throws(FeatureDisabled::class, 'The user-profile [skills] feature is disabled.');

it('uses custom model classes from the configuration', function (): void {
    config()->set('user-profile.models.skill', CustomSkill::class);

    $skill = CustomSkill::query()->create(['name' => 'PHP']);
    $user = user();
    $user->addSkill($skill);

    expect($user->skills()->first())->toBeInstanceOf(CustomSkill::class)
        ->and(UserProfile::searchSkills('php')->first())->toBeInstanceOf(CustomSkill::class);
});

it('rejects configured models that do not extend the package model', function (): void {
    config()->set('user-profile.models.skill', Language::class);

    user()->skills()->get();
})->throws(InvalidArgumentException::class, 'must extend');

it('can disable model validation', function (): void {
    config()->set('user-profile.validation.enabled', false);

    $education = user()->educations()->create(['institution_name' => 'X', 'start_date' => '2020-01-01', 'end_date' => '2019-01-01']);

    expect($education->exists)->toBeTrue();
});

class CustomSkill extends Skill {}
