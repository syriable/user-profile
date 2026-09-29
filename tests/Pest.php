<?php

declare(strict_types=1);

use Syriable\UserProfile\Tests\Fixtures\Seller;
use Syriable\UserProfile\Tests\Fixtures\User;
use Syriable\UserProfile\Tests\TestCase;
use Syriable\UserProfile\Tests\UlidOwnerTestCase;
use Syriable\UserProfile\Tests\UuidOwnerTestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Integration');
pest()->extend(UuidOwnerTestCase::class)->in('UuidOwners');
pest()->extend(UlidOwnerTestCase::class)->in('UlidOwners');

function user(string $name = 'Jane'): User
{
    return User::query()->create(['name' => $name]);
}

function seller(string $name = 'Acme'): Seller
{
    return Seller::query()->create(['name' => $name]);
}

/**
 * Rolls back the package migration, applies configuration changes and runs it
 * again, e.g. to test custom table names or disabled features.
 */
function remigrate(Closure $configure): void
{
    $migration = include __DIR__.'/../database/migrations/create_user_profile_tables.php.stub';

    $migration->down();
    $configure();
    $migration->up();
}
