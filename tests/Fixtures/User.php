<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Concerns\HasUserProfile;

/**
 * @property int $id
 * @property string $name
 */
class User extends Model
{
    use HasUserProfile;

    protected $table = 'users';

    protected $guarded = [];
}
