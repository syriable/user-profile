<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Events;

use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Models\Skill;

final readonly class SkillRemoved
{
    public function __construct(
        public Model $user,
        public Skill $skill,
    ) {}
}
