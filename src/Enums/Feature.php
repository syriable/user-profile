<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Enums;

enum Feature: string
{
    case Languages = 'languages';
    case Skills = 'skills';
    case Education = 'education';
    case Certifications = 'certifications';
    case Awards = 'awards';
}
