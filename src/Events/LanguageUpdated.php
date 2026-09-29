<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Events;

use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\UserLanguage;

final readonly class LanguageUpdated
{
    public function __construct(
        public Model $user,
        public Language $language,
        public UserLanguage $pivot,
    ) {}
}
