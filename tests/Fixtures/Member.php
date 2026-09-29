<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Concerns\HasUserProfile;

/**
 * A user model with a UUID key and a non-default table name.
 *
 * @property string $uuid
 * @property string $name
 */
class Member extends Model
{
    use HasUserProfile;
    use HasUuids;

    protected $table = 'members';

    protected $primaryKey = 'uuid';

    protected $guarded = [];
}
