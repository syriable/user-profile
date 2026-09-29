<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Events;

use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\ProfileLanguage;

final readonly class LanguageUpdated
{
    public function __construct(
        public Model $owner,
        public Language $language,
        public ProfileLanguage $pivot,
    ) {}
}
