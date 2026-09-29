<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ULID-keyed owners: Organization.
 */
class UlidOwnerTestCase extends TestCase
{
    protected function ownerKeyType(): string
    {
        return 'ulid';
    }

    protected function ownerTables(): array
    {
        return ['organizations'];
    }

    protected function createOwnerTables(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
}
