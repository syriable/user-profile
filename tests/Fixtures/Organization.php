<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Concerns\HasUserProfile;

/**
 * An owner with a ULID key.
 *
 * @property string $id
 * @property string $name
 */
class Organization extends Model
{
    use HasUlids;
    use HasUserProfile;

    protected $table = 'organizations';

    protected $guarded = [];
}
