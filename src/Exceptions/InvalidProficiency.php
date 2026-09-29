<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Exceptions;

use InvalidArgumentException;
use Syriable\UserProfile\Support\ProficiencyScale;

final class InvalidProficiency extends InvalidArgumentException implements UserProfileException
{
    public static function unknown(ProficiencyScale $scale, string $level): self
    {
        return new self(sprintf(
            'The %s proficiency level [%s] is not valid. Valid levels: %s.',
            $scale->type->value,
            $level,
            implode(', ', $scale->levels),
        ));
    }

    public static function missing(ProficiencyScale $scale): self
    {
        return new self("A {$scale->type->value} proficiency level is required.");
    }
}
