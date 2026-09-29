<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Concerns\HasUserProfile;

/**
 * A second integer-keyed owner type, sharing IDs with User.
 *
 * @property int $id
 * @property string $name
 */
class Seller extends Model
{
    use HasUserProfile;

    protected $table = 'sellers';

    protected $guarded = [];
}
