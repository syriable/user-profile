<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UUID-keyed owners: Member (primary key "uuid", table "members").
 */
class UuidOwnerTestCase extends TestCase
{
    protected function ownerKeyType(): string
    {
        return 'uuid';
    }

    protected function ownerTables(): array
    {
        return ['members', 'users'];
    }

    protected function createOwnerTables(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->string('name');
            $table->timestamps();
        });

        // An integer-keyed table, to prove incompatible owners are rejected.
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
