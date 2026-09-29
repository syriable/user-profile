<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Syriable\UserProfile\Tests\Fixtures\Member;

/**
 * Runs the package against a user model with a UUID primary key named "uuid"
 * stored in a "members" table.
 */
class CustomUserTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('user-profile.user.model', Member::class);
        $app['config']->set('user-profile.user.key_type', 'uuid');
    }

    protected function createUsersTable(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function dropUsersTable(): void
    {
        Schema::dropIfExists('members');
    }
}
