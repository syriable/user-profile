<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\UserProfile\Tests\Fixtures\User;
use Syriable\UserProfile\UserProfileServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            UserProfileServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('user-profile.user.model', User::class);
        $app['config']->set('user-profile.user.key_type', 'int');

        if (getenv('DB_CONNECTION') === 'testing' || getenv('DB_CONNECTION') === false) {
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->createUsersTable();

        $migration = include __DIR__.'/../database/migrations/create_user_profile_tables.php.stub';
        $migration->up();

        $this->beforeApplicationDestroyed(function () use ($migration): void {
            $migration->down();
            $this->dropUsersTable();
        });
    }

    protected function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function dropUsersTable(): void
    {
        Schema::dropIfExists('users');
    }
}
