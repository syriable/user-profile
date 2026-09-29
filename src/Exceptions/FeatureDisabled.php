<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Exceptions;

use LogicException;
use Syriable\UserProfile\Enums\Feature;

final class FeatureDisabled extends LogicException implements UserProfileException
{
    public static function for(Feature $feature): self
    {
        return new self("The user-profile [{$feature->value}] feature is disabled. Enable it in config/user-profile.php.");
    }
}
