<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Events;

use Illuminate\Database\Eloquent\Model;
use Syriable\UserProfile\Models\ProfileSkill;
use Syriable\UserProfile\Models\Skill;

final readonly class SkillAdded
{
    public function __construct(
        public Model $owner,
        public Skill $skill,
        public ProfileSkill $pivot,
    ) {}
}
