<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Enums;

enum ProficiencyType: string
{
    case Language = 'languages';
    case Skill = 'skills';
}
