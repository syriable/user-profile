<?php

declare(strict_types=1);

namespace Syriable\UserProfile;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\UserProfile\Contracts\LanguageSearchResolver;
use Syriable\UserProfile\Contracts\SkillSearchResolver;
use Syriable\UserProfile\Search\DatabaseLanguageSearchResolver;
use Syriable\UserProfile\Search\DatabaseSkillSearchResolver;

final class UserProfileServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('user-profile')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigration('create_user_profile_tables')
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(UserProfileManager::class);
        $this->app->bindIf(SkillSearchResolver::class, DatabaseSkillSearchResolver::class);
        $this->app->bindIf(LanguageSearchResolver::class, DatabaseLanguageSearchResolver::class);
    }
}
