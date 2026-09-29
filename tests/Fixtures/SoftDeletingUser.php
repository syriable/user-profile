<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Syriable\UserProfile\Concerns\HasUserProfile;

/**
 * @property int $id
 * @property string $name
 */
class SoftDeletingUser extends Model
{
    use HasUserProfile;
    use SoftDeletes;

    protected $table = 'soft_deleting_users';

    protected $guarded = [];
}
