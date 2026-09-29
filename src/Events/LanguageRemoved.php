<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Events;

use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Models\Language;

final readonly class LanguageRemoved
{
    public function __construct(
        public Model $owner,
        public Language $language,
    ) {}
}
