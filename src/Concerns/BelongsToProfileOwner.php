<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Shared polymorphic owner relationship for per-owner profile records.
 *
 * `profileable_type` and `profileable_id` are never mass assignable: records
 * are created through the owner's relationship
 * ($owner->educations()->create([...])), which sets both safely.
 */
trait BelongsToProfileOwner
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function profileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether the record belongs to the given owner. Compares the morph type
     * and key without querying, so an ID shared by two owner types never
     * matches the wrong owner.
     */
    public function isOwnedBy(Model $owner): bool
    {
        $id = $this->getAttribute('profileable_id');
        $key = $owner->getKey();

        return $this->getAttribute('profileable_type') === $owner->getMorphClass()
            && is_scalar($id) && is_scalar($key)
            && (string) $id === (string) $key;
    }
}
