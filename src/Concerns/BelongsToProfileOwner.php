<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * Shared owner relationship for per-user profile records.
 *
 * `user_id` is never mass assignable: records are created through the owner's
 * relationship ($user->educations()->create([...])), which sets it safely.
 */
trait BelongsToProfileOwner
{
    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(PackageConfig::userModel(), 'user_id');
    }

    public function isOwnedBy(Model $user): bool
    {
        return $this->user()->is($user);
    }
}
