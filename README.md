# User Profile for Laravel

[![Tests](https://github.com/syriable/user-profile/actions/workflows/run-tests.yml/badge.svg)](https://github.com/syriable/user-profile/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/syriable/user-profile/actions/workflows/phpstan.yml/badge.svg)](https://github.com/syriable/user-profile/actions/workflows/phpstan.yml)

`syriable/user-profile` adds a structured professional and academic profile to your existing Laravel user model:

- **Languages** a user speaks, with a proficiency level, a native-speaker flag and a primary language.
- **Skills** a user has, with a proficiency level, years of experience and a primary skill.
- **Education** history, **certifications** and **awards**.

Languages and skills are stored in reusable catalogs. Users link to catalog entries through pivot tables, so "JavaScript" exists once no matter how many users list it. Skills can have aliases ("JS", "ECMAScript"), and a search API matches both canonical names and aliases.

The package does not include routes, controllers, views, an admin panel or authorization. It does not replace your `User` model or change authentication. It gives you the data model and a PHP API, and you build the UI your application needs on top.

```php
$user->addSkill('laravel', proficiency: 'expert', yearsOfExperience: 8, isPrimary: true);
$user->addLanguage('ar', isNative: true);
$user->educations()->create(['institution_name' => 'Damascus University', 'degree' => 'Bachelor', 'graduation_year' => 2016]);

UserProfile::searchSkills('JS')->get();            // JavaScript (matched through its alias)
User::whereHasSkill('php', 'advanced')->get();     // users with PHP at "advanced" or above
```

## Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [User model integration](#user-model-integration)
5. [Languages](#languages)
6. [Language proficiency levels](#language-proficiency-levels)
7. [Skills](#skills)
8. [Skill proficiency levels](#skill-proficiency-levels)
9. [Skill aliases and search](#skill-aliases-and-search)
10. [Education history](#education-history)
11. [Certifications](#certifications)
12. [Awards](#awards)
13. [Migrations and data integrity](#migrations-and-data-integrity)
14. [Extending the package](#extending-the-package)
15. [Security and privacy](#security-and-privacy)
16. [Testing](#testing)
17. [Troubleshooting](#troubleshooting)
18. [Architecture decisions and limitations](#architecture-decisions-and-limitations)

## Requirements

| Dependency | Version |
| --- | --- |
| PHP | 8.4+ |
| Laravel | 12.x, 13.x |
| Database | SQLite, MySQL 8+, MariaDB 10.11+, PostgreSQL 14+ |

Search runs as plain SQL with database indexes. It does not need Scout, Elasticsearch, Meilisearch or Algolia.

## Installation

```bash
composer require syriable/user-profile
```

The easiest way to set everything up is the install command. It publishes the config file and the migration, and then asks whether to run the migrations:

```bash
php artisan user-profile:install
```

You can also publish the files yourself:

```bash
php artisan vendor:publish --tag="user-profile-config"
php artisan vendor:publish --tag="user-profile-migrations"
php artisan migrate
```

> **Before you migrate:** check `config/user-profile.php`. The migration uses the configured user model, user key type, table names and enabled features. See [Configuration](#configuration).

To fill the catalogs with starter data (optional), run the seeders:

```bash
php artisan db:seed --class="Syriable\UserProfile\Database\Seeders\LanguageSeeder"
php artisan db:seed --class="Syriable\UserProfile\Database\Seeders\SkillSeeder"
```

`LanguageSeeder` adds about 40 widely spoken languages with native names, BCP 47 codes and ISO 639 codes. `SkillSeeder` adds a small set of skills, categories and aliases. You can run both seeders more than once without creating duplicates. You don't need them at all: your application can create any language or skill it needs.

## Configuration

The published `config/user-profile.php` file contains:

```php
return [
    // The model that owns profile data. Its table and primary key name are
    // read from the model. The key type is "int", "uuid" or "ulid".
    'user' => [
        'model' => App\Models\User::class,
        'key_type' => 'int',
    ],

    // Disabled features are not migrated, and their trait helpers throw
    // Syriable\UserProfile\Exceptions\FeatureDisabled.
    'features' => [
        'languages' => true,
        'skills' => true,
        'education' => true,
        'certifications' => true,
        'awards' => true,
    ],

    // Swap any model for your own subclass.
    'models' => [
        'language' => Syriable\UserProfile\Models\Language::class,
        // user_language, skill, skill_alias, skill_category, user_skill,
        // education, certification, award
    ],

    // Rename tables before migrating if they clash with your own.
    'table_names' => [
        'languages' => 'languages',
        // user_languages, skill_categories, skills, skill_aliases,
        // user_skills, educations, certifications, awards
    ],

    'languages' => [
        'proficiency_levels' => ['beginner', 'elementary', 'intermediate', 'upper_intermediate', 'advanced', 'proficient'],
        'default_proficiency' => null,   // applied when none is given
        'require_proficiency' => false,  // true = a level must be given
    ],

    'skills' => [
        'proficiency_levels' => ['beginner', 'intermediate', 'advanced', 'expert'],
        'default_proficiency' => null,
        'require_proficiency' => false,
    ],

    'search' => [
        'aliases' => true,            // also match skill aliases
        'partial_matching' => true,   // default "contains" matching
        'min_partial_length' => 2,    // shorter terms only match exactly
    ],

    'validation' => [
        'enabled' => true,            // validate package models before saving
    ],
];
```

Labels for proficiency levels are not in the config file. They come from translation files (see [Language proficiency levels](#language-proficiency-levels)).

## User model integration

Add the `HasUserProfile` trait to your user model:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Syriable\UserProfile\Concerns\HasUserProfile;

class User extends Authenticatable
{
    use HasUserProfile;
}
```

The trait adds these relationships:

| Method | Returns | Related model |
| --- | --- | --- |
| `languages()` | `BelongsToMany` (pivot model `UserLanguage`) | `Language` |
| `skills()` | `BelongsToMany` (pivot model `UserSkill`) | `Skill` |
| `educations()` | `HasMany` | `Education` |
| `certifications()` | `HasMany` | `Certification` |
| `awards()` | `HasMany` | `Award` |

It also adds `addLanguage()`, `updateLanguage()`, `removeLanguage()` and `hasLanguage()`, the same four methods for skills, and the `whereHasSkill()` and `whereHasLanguage()` query scopes.

### Using a different user model, table or key type

The package reads the table name and primary key name from your model, so tables such as `members`, or a key named `uuid`, work without extra configuration. If your users have UUID or ULID keys, set the key type **before you run the migration**:

```php
'user' => [
    'model' => App\Models\Member::class,
    'key_type' => 'uuid', // "int", "uuid" or "ulid"
],
```

## Languages

### The language catalog

Each row in `languages` is one language, independent of any user:

| Column | Notes |
| --- | --- |
| `name` | English name, e.g. `Arabic`. Unique, ignoring case and extra spaces. |
| `native_name` | Optional, e.g. `العربية`. |
| `code` | The canonical identifier. Required and unique. Uses a BCP 47 tag such as `en`, `yue`, `pt-BR` or `zh-Hant`, and is stored with conventional casing (`ZH-hant` becomes `zh-Hant`). |
| `iso_639_1` | Optional two-letter code. Many languages don't have one (for example Cantonese, `yue`). |
| `iso_639_3` | Optional three-letter code. |
| `is_active` | Inactive languages stay on existing profiles but don't appear in search. |

Adding a custom language:

```php
use Syriable\UserProfile\Models\Language;

Language::create([
    'name' => 'Syriac',
    'native_name' => 'ܠܫܢܐ ܣܘܪܝܝܐ',
    'code' => 'syc',
    'iso_639_3' => 'syc',
]);
```

The package rejects duplicates such as `ARABIC` next to `Arabic`, or a second language with code `ar`, and throws a `ValidationException`.

### Adding languages to a profile

Methods that take a language accept a `Language` model, its primary key, or its `code` (matched case-insensitively):

```php
$user->addLanguage('ar', isNative: true, isPrimary: true);
$user->addLanguage('en', proficiency: 'advanced');

$user->updateLanguage('en', ['proficiency_level' => 'proficient']);
$user->updateLanguage('en', ['proficiency_level' => null]); // clear it (if not required)

$user->hasLanguage('ar');     // true
$user->removeLanguage('en');  // true; the catalog language is kept

foreach ($user->languages as $language) {
    $language->name;
    $language->pivot->proficiency_level;  // 'advanced' or null
    $language->pivot->proficiencyLabel(); // 'Advanced'
    $language->pivot->is_native;          // bool
    $language->pivot->is_primary;         // bool
}
```

- `addLanguage()` returns the `UserLanguage` pivot. If the language is already on the profile, it throws `DuplicateProfileEntry`.
- `updateLanguage()` accepts `proficiency_level`, `is_native` and `is_primary`. Any other key throws an `InvalidArgumentException`. If the language is not on the profile, it throws `ProfileEntryNotFound`.
- A user has at most one primary language. Setting `is_primary` on one language clears it on the others in the same transaction.

Finding users by language:

```php
User::whereHasLanguage('ar')->get();
User::whereHasLanguage('en', 'advanced')->get(); // advanced or higher, plus native speakers
```

## Language proficiency levels

Proficiency levels are an ordered list in the config file, from lowest to highest. A level's position is its rank, so levels can be sorted and compared. The pivot stores the level key (for example `advanced`).

"Native" is **not** a proficiency level. Native speakers are marked with the separate `is_native` flag. That flag records something different from how well someone uses a language, and keeping it separate means a native speaker can still have a proficiency level (useful for heritage speakers, for example).

A proficiency level is optional unless you set `require_proficiency` to `true`. That supports incomplete profiles.

### Using CEFR

The package includes labels for the CEFR scale (A1–C2). To switch to it, change the levels:

```php
'languages' => [
    'proficiency_levels' => ['a1', 'a2', 'b1', 'b2', 'c1', 'c2'],
],
```

CEFR is kept separate from the descriptive scale. The package never converts between the two, because labels such as "advanced" and "C1" are not exact equivalents. If you change scales after users have saved levels, migrate the stored values yourself.

### Customizing labels

Publish the translations and edit `lang/vendor/user-profile/{locale}/proficiency.php`:

```bash
php artisan vendor:publish --tag="user-profile-translations"
```

If a level has no translation, its label is the key converted to a headline: `world_class` becomes "World Class".

### Working with scales

```php
use Syriable\UserProfile\Facades\UserProfile;

$scale = UserProfile::languageProficiency(); // or UserProfile::skillProficiency()

$scale->levels;                      // ['beginner', ..., 'proficient']
$scale->options();                   // ['beginner' => 'Beginner', ...] for <select> inputs
$scale->has('advanced');             // true
$scale->rank('advanced');            // 4
$scale->compare('advanced', 'beginner'); // 1
$scale->atLeast('advanced');         // ['advanced', 'proficient']
$scale->label('upper_intermediate'); // 'Upper intermediate'
```

In a form request, validate levels with `Rule::in(UserProfile::skillProficiency()->levels)`.

## Skills

### The skill catalog

| Column | Notes |
| --- | --- |
| `name` | The canonical, preferred name, e.g. `JavaScript`. Unique, ignoring case and extra spaces. |
| `slug` | Generated from the name and unique. `C`, `C++` and `C#` become `c`, `c-plus-plus` and `c-sharp`. |
| `description` | Optional. |
| `category_id` | Optional link to a `SkillCategory`. |
| `is_active` | Inactive skills stay on existing profiles but don't appear in search. |

```php
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillCategory;

$category = SkillCategory::create(['name' => 'Programming Languages']); // optional
$skill = Skill::create(['name' => 'Rust', 'category_id' => $category->id]);
```

Categories are optional. A skill without a category works everywhere, and if you delete a category, its skills are kept with `category_id` set to `null`.

The package never merges skills automatically, even when names look alike. `Java` and `JavaScript` are always separate skills.

### Adding skills to a profile

Methods that take a skill accept a `Skill` model, its primary key, or its `slug`:

```php
$user->addSkill('laravel', proficiency: 'expert', yearsOfExperience: 8, isPrimary: true);
$user->addSkill($rust); // proficiency and experience are optional

$user->updateSkill('laravel', ['proficiency_level' => 'advanced', 'years_of_experience' => 9]);

$user->hasSkill('laravel');    // true
$user->removeSkill('laravel'); // true; the catalog skill is kept

$user->skills()->wherePivot('is_primary', true)->first();
```

- `addSkill()` returns the `UserSkill` pivot. If the skill is already on the profile, it throws `DuplicateProfileEntry`.
- `years_of_experience` must be an integer from 0 to 100, or `null`. Proficiency is **never** inferred from experience.
- A user has at most one primary skill.
- `updateSkill()` accepts `proficiency_level`, `years_of_experience` and `is_primary`. A value of the wrong type throws an `InvalidArgumentException` instead of being dropped silently.

Finding users by skill:

```php
User::whereHasSkill('php')->get();
User::whereHasSkill('php', 'advanced')->get(); // "advanced" or "expert"
```

Sorting a profile by proficiency:

```php
$scale = UserProfile::skillProficiency();

$user->skills->sortByDesc(fn ($skill) => $skill->pivot->proficiency_level
    ? $scale->rank($skill->pivot->proficiency_level)
    : -1);
```

## Skill proficiency levels

These work the same way as [language levels](#language-proficiency-levels). The skill scale is set in `skills.proficiency_levels`, and its labels are under `proficiency.skills.*` in the translation file. You can use any terms you like:

```php
'skills' => [
    'proficiency_levels' => ['novice', 'practitioner', 'master'],
],
```

## Skill aliases and search

### Aliases

An alias is another name, abbreviation or spelling of a canonical skill:

```php
$javascript = Skill::create(['name' => 'JavaScript']);

$javascript->addAlias('JS');
$javascript->addAlias('ECMAScript');
$javascript->addAlias('JavaScript', locale: 'de');   // aliases can be tied to a locale

$javascript->removeAlias('ECMAScript');               // returns the number removed
$javascript->aliases;                                 // Collection<SkillAlias>
```

- Every alias belongs to exactly one canonical skill.
- An alias is unique per skill and locale. Calling `addAlias()` again with the same alias returns the existing record.
- An alias does **not** have to be unique across skills. If "JS" genuinely means two different skills in your catalog, add it to both.
- Deleting a skill deletes its aliases.

### Searching skills

```php
use Syriable\UserProfile\Facades\UserProfile;

UserProfile::searchSkills('JS')->get();
UserProfile::searchSkills('script')->paginate(20);
UserProfile::searchSkills('design', category: $designCategory)->limit(10)->get();
UserProfile::searchSkills('seo', locale: 'fr', withAliases: true)->get();
UserProfile::searchSkills('flash', includeInactive: true)->get();
UserProfile::searchSkills('JS', partial: false)->get(); // exact matches only
```

`searchSkills()` returns an Eloquent query builder, so you decide how to run it: `get()`, `paginate()`, `cursorPaginate()`, `limit()` and so on.

| Argument | Default | Meaning |
| --- | --- | --- |
| `term` | – | The search text. |
| `category` | `null` | A `SkillCategory` or category ID to filter by. |
| `partial` | config `search.partial_matching` | `true` = names or aliases *containing* the term. `false` = names or aliases *equal to* the term. |
| `locale` | `null` | Only match aliases with this locale or with no locale. |
| `includeInactive` | `false` | Include skills where `is_active` is false. |
| `withAliases` | `false` | Eager load `aliases` on the results (filtered by `locale` when one is given). |

#### How a search works

1. **Normalization.** The term is normalized the same way names and aliases are when they are saved: Unicode NFKC normalization, whitespace trimmed and collapsed, then lowercased. `'  Project    MANAGEMENT '` matches `Project Management`, and full-width `Ｌａｒａｖｅｌ` matches `Laravel`.
2. **Empty input.** An empty or whitespace-only term returns no results.
3. **Exact vs. partial matching.**
   - *Exact* matching (`partial: false`) finds skills whose normalized name, or any of whose aliases, **equals** the term. Use it to resolve a known abbreviation to canonical skills.
   - *Partial* matching (the default) finds skills whose name or aliases **contain** the term. Use it for autocomplete. Terms shorter than `search.min_partial_length` (default 2) are matched exactly, so a single letter such as `c` finds the skill "C" and not every skill containing a "c". `%` and `_` in the term are treated as literal characters.
4. **No duplicates.** Aliases are matched with `EXISTS` subqueries, not joins, so a skill appears once even when several of its aliases match.
5. **Ordering.** Results are sorted by the first of these conditions that holds, then by name, then by ID. The order is therefore stable and safe to paginate:
   1. the canonical name equals the term
   2. an alias equals the term
   3. the canonical name starts with the term
   4. an alias starts with the term
   5. any other partial match

#### Ambiguous aliases

The package never picks one meaning of an ambiguous alias for you. If "JS" is an alias of both *JavaScript* and *JSON Schema*, `searchSkills('JS', partial: false)` returns **both** canonical skills, and your UI lets the user choose. No undocumented heuristic is involved.

### Searching languages

```php
UserProfile::searchLanguages('Arabic')->get();  // name
UserProfile::searchLanguages('العربية')->get(); // native name
UserProfile::searchLanguages('ar')->get();      // code / ISO 639-1 / ISO 639-3
UserProfile::searchLanguages('ish')->get();     // English, Polish, Spanish, ...
```

Codes are always matched exactly. Names and native names are matched exactly or partially, following the same rules as skills. Code matches are ranked first, then exact name matches, then names that start with the term, then other partial matches.

## Education history

```php
$user->educations()->create([
    'institution_name' => 'Example University', // required
    'type' => 'university',                     // optional, your own taxonomy
    'degree' => 'Bachelor of Science',
    'field_of_study' => 'Computer Science',
    'country_code' => 'SY',                     // ISO 3166-1 alpha-2, stored uppercase
    'city' => 'Aleppo',
    'start_date' => '2012-09-01',
    'end_date' => '2016-06-30',
    'description' => 'Graduated with honours.',
]);

$user->educations()->latestFirst()->get(); // current studies first, then most recent
```

Only `institution_name` is required. The optional `type` column is a free string, so you can store your own values such as `high_school`, `bootcamp` or `online_course`. An entry does not have to be a university degree.

**Graduation date: one source of truth**

- When the exact date is known, `end_date` is the source of truth. Each save derives `graduation_year` from it.
- When only the year is known, set `graduation_year` and leave `end_date` empty.
- For ongoing studies, set `is_current` to `true`. Leave `end_date` empty (setting it fails validation). You can optionally set `graduation_year` to the *expected* year.

**Validation:** `end_date` must be on or after `start_date`, and `graduation_year` must be between 1900 and the current year + 15 and no earlier than the start year. `country_code` must be two letters.

## Certifications

```php
$user->certifications()->create([
    'name' => 'Professional Cloud Architect',        // required
    'issuing_organization' => 'Google',              // required, free text
    'issue_date' => '2024-03-01',
    'expiration_date' => '2026-03-01',               // null = does not expire
    'credential_id' => 'ABC-123',
    'credential_url' => 'https://example.com/verify/ABC-123',
]);

$certification->expires();    // false when there is no expiration date
$certification->isExpired();  // compares against today (or a date you pass)

$user->certifications()->valid()->get();   // not expired, including ones that never expire
$user->certifications()->expired()->get();
```

The issuer is free text, so any provider works: Google, Meta, Microsoft, a university or a local body. `expiration_date` must be on or after `issue_date`. `credential_url` must be an `http` or `https` URL, so values such as `javascript:` URLs are rejected.

> A credential ID or URL is stored as the user entered it. **The package does not verify certifications.** Don't label them "verified" in your UI unless your application has verified them some other way.

## Awards

```php
$user->awards()->create([
    'title' => 'Excellence Award',           // required
    'issuer' => 'Example Organization',
    'date_received' => '2023-05-01',
    'description' => 'For outstanding contributions.',
    'url' => 'https://example.com/awards/2023',
]);
```

Awards are stored separately from certifications, and an award is not treated as a credential. Only `title` is required.

## Migrations and data integrity

### Tables

```text
languages ──< user_languages >── users
skill_categories ──< skills ──< user_skills >── users
                     skills ──< skill_aliases
users ──< educations
users ──< certifications
users ──< awards
```

The package does not create or change the `users` table. The `user_id` columns reference the configured model's table and primary key, and their column type follows the configured key type. All tables are created by one migration, `create_user_profile_tables`.

### Constraints and delete behaviour

| Relationship | On delete |
| --- | --- |
| user → `user_languages`, `user_skills`, `educations`, `certifications`, `awards` | **cascade**: a user's own profile data is deleted with the user |
| `languages` → `user_languages` | **restrict**: you can't delete a language that a profile uses |
| `skills` → `user_skills` | **restrict**: you can't delete a skill that a profile uses |
| `skills` → `skill_aliases` | cascade |
| `skill_categories` → `skills` | set null |

To retire a language or skill without touching users' profiles, set `is_active` to `false`. It disappears from search but stays on existing profiles.

Uniqueness:

- A user can't have the same language twice or the same skill twice: the composite primary key is `(user_id, language_id)` / `(user_id, skill_id)`.
- Skill and language names are unique after normalization.
- Language `code`, `iso_639_1` and `iso_639_3` are each unique.
- Skill slugs are unique.
- Aliases are unique per `(skill_id, normalized_alias, locale)`.

### Validation

Every package model validates its attributes before it saves, whichever API wrote them, and throws Laravel's `ValidationException`. The check is built into `save()` rather than a model event, so it still runs when your tests call `Event::fake()`.

Each model exposes its rules for reuse in form requests:

```php
public function rules(): array
{
    return (new \Syriable\UserProfile\Models\Education)->rules();
}
```

Date columns are cast. A malformed date string therefore fails as soon as it is assigned to a model, before model validation can report it. Validate request input with the rules above first.

To turn model validation off (not recommended), set `validation.enabled` to `false`.

### Transactions

Adding or updating a profile language or skill runs in a transaction: the duplicate check, clearing the previous primary entry and the write all succeed or fail together. If a concurrent request inserts the same entry first, the database's unique constraint violation is converted to `DuplicateProfileEntry`.

### Customizing the migration

The migration is published to your application, so you can edit it: add columns, change string lengths, or remove tables for features you don't use. To remove whole features without editing it, turn them off under `features` **before** migrating. If you add columns, extend the matching model through the `models` config and add the new columns to its `$fillable`.

## Extending the package

### Custom models

```php
namespace App\Models;

use Syriable\UserProfile\Models\Skill as BaseSkill;

class Skill extends BaseSkill
{
    public function endorsements() { /* ... */ }
}
```

```php
// config/user-profile.php
'models' => [
    'skill' => App\Models\Skill::class,
],
```

All relationships, searches and trait helpers then return your model. A configured model must extend the package model it replaces.

### Custom search resolvers

Search logic lives behind two small interfaces, `Syriable\UserProfile\Contracts\SkillSearchResolver` and `LanguageSearchResolver`. Each takes an immutable criteria object and returns a query builder. Register your own implementation in a service provider:

```php
use Illuminate\Database\Eloquent\Builder;
use Syriable\UserProfile\Facades\UserProfile;
use Syriable\UserProfile\Search\DatabaseSkillSearchResolver;
use Syriable\UserProfile\Search\SkillSearchCriteria;

class PopularFirstSkillSearch extends DatabaseSkillSearchResolver
{
    public function search(SkillSearchCriteria $criteria): Builder
    {
        return parent::search($criteria)->reorder()
            ->withCount('users')
            ->orderByDesc('users_count')
            ->orderBy('name');
    }
}

// AppServiceProvider::boot()
UserProfile::registerSkillSearchResolver(PopularFirstSkillSearch::class);
```

`registerSkillSearchResolver()` and `registerLanguageSearchResolver()` accept a class name or an instance. You can also bind the interfaces in the container yourself.

A custom resolver must still return each canonical skill at most once and in a deterministic order, so that pagination stays correct. `SkillSearchCriteria` includes the normalized term, the partial and alias flags, the category, the locale, the inactive flag and the eager-loading flag.

### Events

| Event | Properties |
| --- | --- |
| `Syriable\UserProfile\Events\SkillAdded` / `SkillUpdated` | `$user`, `$skill`, `$pivot` |
| `Syriable\UserProfile\Events\SkillRemoved` | `$user`, `$skill` |
| `Syriable\UserProfile\Events\LanguageAdded` / `LanguageUpdated` | `$user`, `$language`, `$pivot` |
| `Syriable\UserProfile\Events\LanguageRemoved` | `$user`, `$language` |

Education, certification and award records are ordinary Eloquent models, so you can use the standard model events and observers for them.

### Exceptions

Every package exception implements `Syriable\UserProfile\Exceptions\UserProfileException`:

| Exception | Thrown when |
| --- | --- |
| `DuplicateProfileEntry` | A language or skill is already on the profile. |
| `ProfileEntryNotFound` | A language or skill isn't in the catalog, or isn't on the profile being updated. |
| `InvalidProficiency` | A proficiency level isn't in the configured scale, or is required but missing. |
| `FeatureDisabled` | A helper for a disabled feature is called. |

## Security and privacy

- **No routes, no authorization.** The package does not expose endpoints. Access control belongs to your application: use policies and gates as usual.

  ```php
  // app/Policies/EducationPolicy.php
  public function update(User $user, Education $education): bool
  {
      return $education->isOwnedBy($user);
  }
  ```

- **The owner is never mass assignable.** `user_id` is not fillable on `Education`, `Certification` or `Award`. Create records through the owner's relationship, for example `$request->user()->educations()->create($validated)`. A `user_id` in request input is then ignored. Pivot helpers only accept a whitelist of attributes.
- **Scope lookups to the owner.** Use `$user->educations()->findOrFail($id)` rather than `Education::findOrFail($id)`, so users can't reach other users' records by ID. `updateSkill()`, `updateLanguage()`, `removeSkill()` and `removeLanguage()` only touch the calling user's pivot rows.
- **Visibility is yours to define.** Nothing is public by default. The package has no "public profile" concept, so you choose which fields to show through your API resources or views.
- **Minimal personal data.** The package stores professional data only. It doesn't collect dates of birth, addresses or contact details.
- **User claims are not verified facts.** Certifications, awards and skill levels are self-reported unless your application verifies them.
- **Safe URLs.** Only `http` and `https` URLs are accepted, which blocks `javascript:` URLs. Still escape output as usual.
- **SQL.** Search terms are always passed as bindings. The only raw SQL is fixed, literal SQL.

## Testing

```bash
composer test       # Pest
composer analyse    # PHPStan (level max, Larastan)
composer format     # Laravel Pint
composer refactor   # Rector
```

By default, the tests run on in-memory SQLite. To run them against another database, set the usual `DB_*` variables:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=user_profile \
DB_USERNAME=postgres DB_PASSWORD=secret vendor/bin/pest
```

CI runs the suite on PHP 8.4 and 8.5, Laravel 12 and 13, and SQLite, MySQL 8.4, MariaDB 11 and PostgreSQL 17.

The package includes model factories you can use in your own tests:

```php
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\Education;

$skill = Skill::factory()->withAliases(['JS'])->create(['name' => 'JavaScript']);
Education::factory()->for($user, 'user')->current()->create();
```

## Troubleshooting

**`No table name is configured for [user-profile.table_names.…]`**
Your published config is missing a key that a newer version added. Compare it with the package's `config/user-profile.php`.

**`SQLSTATE… foreign key constraint` when migrating**
The `users` table (or your configured user table) must exist before this migration runs, and `user.key_type` must match its primary key type. Give the published migration a later timestamp than your users migration.

**`The model configured for [user-profile.models.skill] must extend …`**
A custom model must extend the package model it replaces.

**`FeatureDisabled`**
You called a helper for a feature that is turned off under `features`. Turn the feature on and create its tables (re-publish the migration, or write a new one) before using it.

**A search returns nothing for a single character**
Terms shorter than `search.min_partial_length` are matched exactly. Lower the setting, or pass `partial: false` on purpose.

**`Could not parse '…'` when saving a date**
Laravel's date casts parse values when they are assigned. Validate input with the model's `rules()` before creating the record.

**Accented names clash on MySQL/MariaDB**
With the default `utf8mb4_unicode_ci` collation, MySQL compares `École` and `Ecole` as equal. The unique indexes therefore treat them as duplicates, and exact matches ignore accents. PostgreSQL and SQLite treat them as different. Choose a collation that fits your catalog if this matters.

## Architecture decisions and limitations

See [`docs/architecture.md`](docs/architecture.md) for the reasoning behind the schema, the proficiency model, the search design and the extension API, along with known limitations.

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

The MIT License (MIT). See [License File](LICENSE.md).
