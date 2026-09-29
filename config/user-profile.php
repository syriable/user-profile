<?php

declare(strict_types=1);
use Syriable\UserProfile\Models\Award;
use Syriable\UserProfile\Models\Certification;
use Syriable\UserProfile\Models\Education;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Models\ProfileLanguage;
use Syriable\UserProfile\Models\ProfileSkill;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillAlias;
use Syriable\UserProfile\Models\SkillCategory;

return [

    /*
    |--------------------------------------------------------------------------
    | Profile owner key type
    |--------------------------------------------------------------------------
    |
    | Profile data belongs to any Eloquent model using the HasUserProfile
    | trait, through a polymorphic `profileable_type` / `profileable_id` pair.
    |
    | A database column has exactly one native type, so every profile owner
    | model must use the same primary key type: "int", "uuid" or "ulid". Mixing
    | integer and string keys in one column breaks on PostgreSQL and prevents
    | index use on MySQL, so it is rejected at runtime instead of half-working.
    | Set this before running the migration.
    |
    */

    'owner_key_type' => 'int',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Disabled features are not migrated and their mutating helpers on the
    | HasUserProfile trait throw a FeatureDisabled exception.
    |
    */

    'features' => [
        'languages' => true,
        'skills' => true,
        'education' => true,
        'certifications' => true,
        'awards' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Replace any model with your own subclass to add relationships, scopes or
    | casts. Custom models must extend the package model they replace.
    |
    */

    'models' => [
        'language' => Language::class,
        'profile_language' => ProfileLanguage::class,
        'skill' => Skill::class,
        'skill_alias' => SkillAlias::class,
        'skill_category' => SkillCategory::class,
        'profile_skill' => ProfileSkill::class,
        'education' => Education::class,
        'certification' => Certification::class,
        'award' => Award::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | Change these *before* running the migrations if they clash with tables
    | that already exist in your application.
    |
    */

    'table_names' => [
        'languages' => 'languages',
        'profile_languages' => 'profile_languages',
        'skill_categories' => 'skill_categories',
        'skills' => 'skills',
        'skill_aliases' => 'skill_aliases',
        'profile_skills' => 'profile_skills',
        'educations' => 'educations',
        'certifications' => 'certifications',
        'awards' => 'awards',
    ],

    /*
    |--------------------------------------------------------------------------
    | Language proficiency
    |--------------------------------------------------------------------------
    |
    | Levels are ordered from lowest to highest; the position of a level is its
    | rank for sorting and comparison. Labels live in the translation file
    | (user-profile::proficiency.languages.<level>).
    |
    | Native speakers are flagged with the separate `is_native` pivot column,
    | so "native" is intentionally not a proficiency level.
    |
    | To use the CEFR scale instead, set the levels to:
    | ['a1', 'a2', 'b1', 'b2', 'c1', 'c2']
    |
    */

    'languages' => [
        'proficiency_levels' => [
            'beginner',
            'elementary',
            'intermediate',
            'upper_intermediate',
            'advanced',
            'proficient',
        ],
        'default_proficiency' => null,
        'require_proficiency' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Skill proficiency
    |--------------------------------------------------------------------------
    */

    'skills' => [
        'proficiency_levels' => [
            'beginner',
            'intermediate',
            'advanced',
            'expert',
        ],
        'default_proficiency' => null,
        'require_proficiency' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | aliases:            match skill aliases in addition to canonical names.
    | partial_matching:   default for "contains" matching; exact matching is
    |                     used when disabled or requested per search.
    | min_partial_length: terms shorter than this only match exactly.
    |
    */

    'search' => [
        'aliases' => true,
        'partial_matching' => true,
        'min_partial_length' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | When enabled, package models validate their attributes before saving and
    | throw an Illuminate\Validation\ValidationException on invalid data.
    |
    */

    'validation' => [
        'enabled' => true,
    ],

];
