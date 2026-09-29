<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Exceptions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a write receives a term (such as an alias) that names more than
 * one catalog entry. The package never picks one of them on its own.
 */
final class AmbiguousProfileEntry extends RuntimeException implements UserProfileException
{
    /**
     * @param  Collection<int, covariant Model>  $candidates
     */
    public function __construct(
        public readonly string $term,
        /** @var Collection<int, covariant Model> */
        public readonly Collection $candidates,
    ) {
        parent::__construct(sprintf(
            'The term [%s] matches %d catalog entries (IDs: %s). Pass the intended model or its key instead.',
            $term,
            $candidates->count(),
            $candidates->modelKeys() === [] ? '-' : implode(', ', array_map(strval(...), $candidates->modelKeys())),
        ));
    }
}
