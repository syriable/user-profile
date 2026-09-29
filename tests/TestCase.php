<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\UserProfile\UserProfileServiceProvider;

/**
 * Integer-keyed owners: User, Seller and SoftDeletingUser.
 */
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
        $app['config']->set('user-profile.owner_key_type', $this->ownerKeyType());

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
        $migration = include __DIR__.'/../database/migrations/create_user_profile_tables.php.stub';

        // Registered first, so a failing migration can't leave tables behind
        // for the next test on persistent databases.
        $this->beforeApplicationDestroyed(function () use ($migration): void {
            $migration->down();

            foreach ($this->ownerTables() as $table) {
                Schema::dropIfExists($table);
            }
        });

        $this->createOwnerTables();
        $migration->up();
    }

    protected function ownerKeyType(): string
    {
        return 'int';
    }

    /**
     * @return list<string>
     */
    protected function ownerTables(): array
    {
        return ['users', 'sellers', 'soft_deleting_users'];
    }

    protected function createOwnerTables(): void
    {
        foreach ($this->ownerTables() as $table) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();

                if ($table->getTable() === 'soft_deleting_users') {
                    $table->softDeletes();
                }
            });
        }
    }
}
