<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Events;

use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\UserSkill;

final readonly class SkillUpdated
{
    public function __construct(
        public Model $user,
        public Skill $skill,
        public UserSkill $pivot,
    ) {}
}
