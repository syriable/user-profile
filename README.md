# User Profile for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/syriable/user-profile.svg)](https://packagist.org/packages/syriable/user-profile)
[![Tests](https://github.com/syriable/user-profile/actions/workflows/run-tests.yml/badge.svg)](https://github.com/syriable/user-profile/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/syriable/user-profile/actions/workflows/phpstan.yml/badge.svg)](https://github.com/syriable/user-profile/actions/workflows/phpstan.yml)

`syriable/user-profile` adds a structured professional and academic profile to any Eloquent model, such as `User`, `Seller`, `Freelancer` or `Company`:

- **Languages** the owner speaks, with a proficiency level, a native-speaker flag and a primary language.
- **Skills** the owner has, with a proficiency level, years of experience and a primary skill.
- **Education** history, **certifications** and **awards**.

Languages and skills are shared catalogs, so "JavaScript" is stored once however many profiles list it. Skills can have aliases ("JS", "ECMAScript"). Profile data belongs to its owner through a standard Laravel polymorphic relationship, so several models can have profiles in the same application.

The package also lets you **find owners by their profile**, for example "sellers who speak English at an advanced level and know PHP". The filtering runs in the database and returns an ordinary Eloquent query that you can paginate.

The package doesn't include routes, controllers, views, an admin panel or authorization, and it doesn't change your models' authentication. It provides the data model and a PHP API, and you build the interface your application needs on top.

```php
$user->addSkill('Laravel', proficiency: 'expert', yearsOfExperience: 8, isPrimary: true);
$user->addLanguage('Arabic', isNative: true);
$seller->addSkill('JS', proficiency: 'advanced'); // the alias resolves to JavaScript

User::query()
    ->whereLanguage('English', atLeast: 'intermediate')
    ->whereAllSkills(['PHP', 'Laravel'])
    ->withoutSkill('WordPress')
    ->paginate(24);

UserProfile::searchSkills('scr')->get(); // catalog autocomplete: JavaScript, TypeScript, ...
```

## Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Profile owners](#profile-owners)
5. [Languages](#languages)
6. [Skills](#skills)
7. [Proficiency levels](#proficiency-levels)
8. [Skill aliases and catalog search](#skill-aliases-and-catalog-search)
9. [Finding profile owners](#finding-profile-owners)
10. [Education history](#education-history)
11. [Certifications](#certifications)
12. [Awards](#awards)
13. [Database design, integrity and performance](#database-design-integrity-and-performance)
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

All searching and filtering runs as plain SQL against indexed tables. The package doesn't need Scout, Elasticsearch, Meilisearch, Algolia or Typesense.

## Installation

```bash
composer require syriable/user-profile
```

The install command publishes the config file and the migration, then asks whether to run the migrations:

```bash
php artisan user-profile:install
```

You can also publish the files yourself:

```bash
php artisan vendor:publish --tag="user-profile-config"
php artisan vendor:publish --tag="user-profile-migrations"
php artisan migrate
```

> **Before you migrate**, set `owner_key_type` in `config/user-profile.php` to match your owner models' primary keys (`int`, `uuid` or `ulid`). See [Primary key types](#primary-key-types). The migration also reads the configured table names and enabled features.

The migration doesn't touch your `users` table or any other owner table, and it doesn't depend on them. It can therefore run before or after your own migrations.

To fill the catalogs with starter data (optional), run the seeders:

```bash
php artisan db:seed --class="Syriable\UserProfile\Database\Seeders\LanguageSeeder"
php artisan db:seed --class="Syriable\UserProfile\Database\Seeders\SkillSeeder"
```

`LanguageSeeder` adds about 40 widely spoken languages with native names, BCP 47 codes and ISO 639 codes. `SkillSeeder` adds a small set of skills, categories and aliases. You can run both seeders more than once without creating duplicates, and your application can add any language or skill the seeders don't include.

## Configuration

```php
return [
    // The primary key type shared by every profile owner model:
    // "int", "uuid" or "ulid". Set this before migrating.
    'owner_key_type' => 'int',

    // Disabled features are not migrated, and their helpers and filters throw
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
        // profile_language, skill, skill_alias, skill_category, profile_skill,
        // education, certification, award
    ],

    // Rename tables before migrating if they clash with your own.
    'table_names' => [
        'languages' => 'languages',
        // profile_languages, skill_categories, skills, skill_aliases,
        // profile_skills, educations, certifications, awards
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
        'aliases' => true,            // match skill aliases
        'partial_matching' => true,   // default for catalog search
        'min_partial_length' => 2,    // shorter terms only match exactly
    ],

    'validation' => [
        'enabled' => true,            // validate package models before saving
    ],
];
```

The config doesn't name a user model. Any model that uses the trait becomes a profile owner.

## Profile owners

Add the `HasUserProfile` trait to every model that should have a profile. You don't need a special base class:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Syriable\UserProfile\Concerns\HasUserProfile;

class User extends Authenticatable
{
    use HasUserProfile;
}

class Seller extends Model
{
    use HasUserProfile;
}
```

Each owner gets these relationships:

| Method | Returns | Related model |
| --- | --- | --- |
| `languages()` | `MorphToMany` (pivot model `ProfileLanguage`) | `Language` |
| `skills()` | `MorphToMany` (pivot model `ProfileSkill`) | `Skill` |
| `educations()` | `MorphMany` | `Education` |
| `certifications()` | `MorphMany` | `Certification` |
| `awards()` | `MorphMany` | `Award` |
| `profileLanguages()` | `MorphMany` of the language pivot rows | `ProfileLanguage` |
| `profileSkills()` | `MorphMany` of the skill pivot rows | `ProfileSkill` |

```php
$user->skills;                 // Collection<Skill>, pivot data on ->pivot
$seller->languages;
$user->educations()->latestFirst()->get();

User::with(['skills', 'languages'])->paginate(); // eager loading, no N+1
```

The inverse relationships are named `profileable`:

```php
$education->profileable;                    // the User, Seller, ... that owns it
$skill->profileSkills()->with('profileable')->get(); // who lists this skill, across owner types
```

Ownership is recorded in `profileable_type` + `profileable_id`, so `User` #1 and `Seller` #1 have completely separate profiles.

### Primary key types

`profileable_id` is a single database column, and a column has one native type. So **every profile owner model in an application must use the same primary key type**, which you set with `owner_key_type`:

| `owner_key_type` | Column | Owner models |
| --- | --- | --- |
| `int` (default) | `unsigned bigint` | Auto-incrementing or other integer keys |
| `uuid` | `uuid` (native on PostgreSQL, `char(36)` elsewhere) | `HasUuids`, or any UUID string key |
| `ulid` | `char(26)` | `HasUlids`, or any ULID string key |

The key's column name doesn't matter: a UUID key named `uuid` works. The package reads it from the model.

**Mixing key types (for example integer `User` and UUID `Seller`) isn't supported,** and the package rejects it instead of letting it half-work. A string column holding both kinds of key would break on PostgreSQL (`varchar = bigint` has no operator, which affects every `whereHas` and every eager load of integer keys). On MySQL/MariaDB it would silently compare every row numerically and stop using the index. When an owner model's key type doesn't match the configuration, the first profile relationship you call throws `IncompatibleProfileOwner` with an explanation.

If your owners genuinely use different key types, give them a common key type (for example, a UUID column on each owner), or make one model the profile owner and relate the others to it.

### Morph maps

The package doesn't register a morph map and doesn't need one. It stores whatever `getMorphClass()` returns, so it follows your application's `Relation::morphMap()` or `Relation::enforceMorphMap()` configuration automatically. If you add a morph map to an application that already has profile data, update the stored `profileable_type` values, as you would for any other polymorphic relationship.

### Deleting owners

A polymorphic column can't have a foreign key to several tables, so the database can't cascade deletes to profile data. The trait handles cleanup instead:

- Deleting an owner (`$user->delete()`) deletes its pivot rows, education, certifications and awards in one transaction. Catalog languages and skills are kept.
- **Soft-deleting** owners keep their profile while trashed and restored. It is removed only when the owner is force deleted.
- Query builder deletes (`User::where(...)->delete()`) fire no Eloquent events, so they don't clean up. Call `$owner->deleteProfile()` for those owners first.
- `Event::fake()` also fakes Eloquent events, as it does for any model listener. In tests that fake events, call `deleteProfile()` yourself or fake only specific events.

## Languages

### The language catalog

Each row in `languages` is one language, independent of any owner:

| Column | Notes |
| --- | --- |
| `name` | English name, e.g. `Arabic`. Unique, ignoring case and extra spaces. |
| `native_name` | Optional, e.g. `العربية`. |
| `code` | Required, unique, and the canonical identifier. A BCP 47 tag such as `en`, `yue`, `pt-BR` or `zh-Hant`, stored with conventional casing. |
| `iso_639_1` | Optional two-letter code. Many languages don't have one (for example Cantonese, `yue`). |
| `iso_639_3` | Optional three-letter code. |
| `is_active` | Inactive languages stay on existing profiles but don't appear in catalog search. |

```php
use Syriable\UserProfile\Models\Language;

Language::create(['name' => 'Syriac', 'native_name' => 'ܠܫܢܐ ܣܘܪܝܝܐ', 'code' => 'syc', 'iso_639_3' => 'syc']);
```

### Managing an owner's languages

A language can be passed as a `Language` model, its primary key, or a term: its code, ISO 639 code, name or native name (see [How terms are resolved](#how-terms-are-resolved)).

```php
$user->addLanguage('Arabic', isNative: true, isPrimary: true);
$user->addLanguage('en', proficiency: 'advanced');

$user->updateLanguage('en', ['proficiency_level' => 'proficient']);
$user->updateLanguage('en', ['proficiency_level' => null]); // clear it, if not required

$user->hasLanguage('English'); // true
$user->removeLanguage('en');   // true; the catalog language is kept

foreach ($user->languages as $language) {
    $language->pivot->proficiency_level;  // 'advanced' or null
    $language->pivot->proficiencyLabel(); // 'Advanced'
    $language->pivot->is_native;
    $language->pivot->is_primary;
}
```

- `addLanguage()` returns the `ProfileLanguage` pivot. If the language is already on the profile, it throws `DuplicateProfileEntry`.
- `updateLanguage()` accepts `proficiency_level`, `is_native` and `is_primary`. Any other key, or a value of the wrong type, throws an `InvalidArgumentException`. If the language isn't on the profile, it throws `ProfileEntryNotFound`.
- An owner has at most one primary language. Setting `is_primary` on one language clears it on the others in the same transaction.

## Skills

### The skill catalog

| Column | Notes |
| --- | --- |
| `name` | The canonical, preferred name, e.g. `JavaScript`. Unique, ignoring case and extra spaces. |
| `slug` | Generated and unique. `C`, `C++` and `C#` become `c`, `c-plus-plus` and `c-sharp`. |
| `description` | Optional. |
| `category_id` | Optional `SkillCategory`. |
| `is_active` | Inactive skills stay on existing profiles but don't appear in catalog search. |

```php
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillCategory;

$category = SkillCategory::create(['name' => 'Programming Languages']); // optional
Skill::create(['name' => 'Rust', 'category_id' => $category->id]);
```

The package never merges skills automatically. `Java` and `JavaScript` are always separate skills.

### Managing an owner's skills

A skill can be passed as a `Skill` model, its primary key, or a term: its name, slug or an alias (see [How terms are resolved](#how-terms-are-resolved)).

```php
$user->addSkill('Laravel', proficiency: 'expert', yearsOfExperience: 8, isPrimary: true);
$user->addSkill('ECMAScript'); // alias of JavaScript; proficiency and experience are optional

$user->updateSkill('laravel', ['proficiency_level' => 'advanced', 'years_of_experience' => 9]);

$user->hasSkill('JavaScript'); // true
$user->removeSkill('Laravel'); // true; the catalog skill is kept
```

- `addSkill()` returns the `ProfileSkill` pivot. If the skill is already on the profile, it throws `DuplicateProfileEntry`.
- `years_of_experience` must be an integer from 0 to 100, or `null`. Proficiency is never inferred from experience.
- An owner has at most one primary skill.

### How terms are resolved

A string is resolved against the catalog by `UserProfile::resolveSkills()` / `UserProfile::resolveLanguages()`. Both use the same normalization and alias rules as catalog search in *exact* mode, so aliases are resolved in only one place. Inactive entries are included, because they can still be on profiles.

```php
UserProfile::resolveSkills('js');          // Collection: [JavaScript]
UserProfile::resolveSkills('c-plus-plus'); // Collection: [C++] (slug)
UserProfile::resolveLanguages('ara');      // Collection: [Arabic] (ISO 639-3)
```

An alias can name more than one skill. If "JS" is an alias of both *JavaScript* and *JSON Schema*, then:

- **writes refuse to guess.** `addSkill('JS')`, `updateSkill('JS', …)` and `removeSkill('JS')` throw `AmbiguousProfileEntry`, and its `$candidates` property holds every matching skill so your UI can ask the user to choose;
- **reads and filters use every candidate.** `hasSkill('JS')` and `whereSkill('JS')` match owners with *either* skill. No single meaning is picked.

A term that matches nothing throws `ProfileEntryNotFound` on writes, and matches no owners in filters.

## Proficiency levels

Proficiency levels are ordered lists in the config file, from lowest to highest. A level's position is its rank, which is how levels are sorted, compared and filtered with `atLeast:`. Levels are never compared alphabetically. The pivot stores the level key (for example `advanced`).

```php
use Syriable\UserProfile\Facades\UserProfile;

$scale = UserProfile::skillProficiency(); // or UserProfile::languageProficiency()

$scale->levels;                          // ['beginner', 'intermediate', 'advanced', 'expert']
$scale->options();                       // ['beginner' => 'Beginner', ...] for <select> inputs
$scale->rank('advanced');                // 2
$scale->compare('advanced', 'beginner'); // 1
$scale->atLeast('advanced');             // ['advanced', 'expert']
```

- **Native isn't a level.** Native speakers are marked with the separate `is_native` flag, because nativeness and proficiency are different things.
- **Optional by default.** A proficiency level is optional unless you set `require_proficiency`, so incomplete profiles are allowed. `default_proficiency` is applied when none is given.
- **CEFR.** Labels for A1–C2 are included. To use CEFR, set `languages.proficiency_levels` to `['a1', 'a2', 'b1', 'b2', 'c1', 'c2']`. The package never converts between CEFR and descriptive levels.
- **Labels** come from translations. Publish them with `php artisan vendor:publish --tag="user-profile-translations"` and edit `lang/vendor/user-profile/{locale}/proficiency.php`. A level without a translation is shown as a headline (`world_class` becomes "World Class").
- In form requests, validate levels with `Rule::in(UserProfile::skillProficiency()->levels)`.

## Skill aliases and catalog search

### Aliases

```php
$javascript = Skill::create(['name' => 'JavaScript']);

$javascript->addAlias('JS');
$javascript->addAlias('ECMAScript');
$javascript->addAlias('JavaScript', locale: 'de'); // aliases can be tied to a locale
$javascript->removeAlias('ECMAScript');            // returns the number removed
```

Every alias belongs to exactly one canonical skill, and is unique per skill and locale. An alias doesn't have to be unique across skills, so the same alias can be added to several skills when it genuinely has several meanings. Deleting a skill deletes its aliases.

### Searching the catalog

Catalog search finds *skills and languages*, for example to power an autocomplete input. To find *owners*, see [Finding profile owners](#finding-profile-owners).

```php
UserProfile::searchSkills('scr')->get();                         // partial: JavaScript, TypeScript
UserProfile::searchSkills('JS', partial: false)->get();          // exact names and aliases only
UserProfile::searchSkills('design', category: $design)->paginate(20);
UserProfile::searchSkills('seo', locale: 'fr', withAliases: true)->get();
UserProfile::searchLanguages('ara')->get();                      // names, native names and codes
```

Both methods return an Eloquent query builder.

| Argument | Default | Meaning |
| --- | --- | --- |
| `partial` | config `search.partial_matching` | `true` = names or aliases *containing* the term. `false` = names or aliases *equal to* it. |
| `category` | `null` | A `SkillCategory` or category ID to filter by. |
| `locale` | `null` | Only match aliases with this locale or with no locale. |
| `includeInactive` | `false` | Include inactive entries. |
| `withAliases` | `false` | Eager load `aliases` on the results. |

How it works:

- **Normalization.** Search terms and stored names are normalized the same way: Unicode NFKC normalization, whitespace trimmed and collapsed, then lowercased.
- **Empty or short terms.** Empty input returns nothing. Terms shorter than `min_partial_length` only match exactly.
- **Literal wildcards.** `%` and `_` in a term are treated as ordinary characters.
- **No duplicates.** Aliases are matched with `EXISTS`, not joins, so a skill never appears twice.
- **Stable order.** Results are ordered: exact name, exact alias, name prefix, alias prefix, other matches, then name, then ID. This makes pagination stable.
- **Ambiguity.** An alias with several meanings returns every skill it names.

## Finding profile owners

Filtering is done with query scopes that the trait adds to every owner model. Each scope adds one `EXISTS` / `NOT EXISTS` condition on the owner's pivot rows, and that condition always includes the owner's morph type. The result is the owner's own Eloquent query, so you can chain your own conditions, eager load and paginate, and nothing is filtered in PHP.

```php
use App\Models\Seller;
use App\Models\User;

User::query()->whereSkill('PHP')->get();

Seller::query()
    ->whereLanguage('English', atLeast: 'intermediate')
    ->whereSkill('PHP')
    ->where('country', 'SY') // your own columns
    ->with('skills')
    ->paginate(24);
```

### Skill filters

| Scope | Matches owners who… |
| --- | --- |
| `whereSkill($skill, atLeast: null, primary: null)` | have the skill |
| `whereAnySkill([$a, $b], atLeast: null)` | have **at least one** of the skills |
| `whereAllSkills([$a, $b], atLeast: null)` | have **every** skill |
| `withoutSkill($skill)` | don't have the skill |

- `atLeast: 'advanced'` means "advanced or above" on the configured skill scale. Skills without a level don't meet a threshold. An unknown level throws `InvalidProficiency` before any query runs.
- `primary: true` restricts the match to the owner's primary skill (`false` excludes it).

### Language filters

| Scope | Matches owners who… |
| --- | --- |
| `whereLanguage($language, atLeast: null, native: null, primary: null)` | speak the language |
| `whereAnyLanguage([$a, $b], atLeast: null)` | speak **at least one** of the languages |
| `whereAllLanguages([$a, $b], atLeast: null)` | speak **every** language |
| `withoutLanguage($language)` | don't speak the language |

- Native speakers always meet an `atLeast:` threshold.
- `native: true` / `false` restricts the match to native or non-native speakers.
- `primary: true` restricts the match to the owner's primary language.

Skills and languages can be passed as models, IDs or terms, exactly as for writes (see [How terms are resolved](#how-terms-are-resolved)).

### AND, OR and NOT

- **Chained scopes combine with AND:**

  ```php
  // English AND PHP AND Laravel
  User::query()->whereLanguage('English')->whereSkill('PHP')->whereSkill('Laravel');
  ```

- **"Any" scopes express OR within one kind of filter:**

  ```php
  // English AND (PHP OR JavaScript)
  User::query()->whereLanguage('English')->whereAnySkill(['PHP', 'JavaScript']);
  ```

- **Other groupings use ordinary `where` closures,** because the scopes work inside them like any other Eloquent scope:

  ```php
  // French speakers OR (English speakers with advanced PHP)
  User::query()->where(fn ($query) => $query
      ->whereLanguage('French')
      ->orWhere(fn ($query) => $query->whereLanguage('English')->whereSkill('PHP', atLeast: 'advanced')));
  ```

- **NOT:** `withoutSkill()` / `withoutLanguage()`.

Edge cases:

| Input | Result |
| --- | --- |
| Unknown skill or language | `where*` matches no owners; `without*` excludes nobody |
| `whereAnySkill([])` / `whereAnyLanguage([])` | matches no owners, like `whereIn([])` |
| `whereAllSkills([])` / `whereAllLanguages([])` | adds no condition |
| Ambiguous alias | matches owners with any skill the alias names |

### How filtering runs

```php
User::query()->whereSkill('PHP', atLeast: 'advanced')->whereLanguage('English')->paginate(24);
```

1. Each term is resolved to catalog IDs with one small indexed query (`resolveSkills` / `resolveLanguages`). If you pass models or IDs, no lookup query runs.
2. A single owner query is built:

   ```sql
   select * from users
   where exists (select * from profile_skills
                 where users.id = profile_skills.profileable_id
                   and profile_skills.profileable_type = 'App\Models\User'
                   and profile_skills.skill_id in (?)
                   and profile_skills.proficiency_level in ('advanced', 'expert'))
     and exists (select * from profile_languages where ...)
   limit 24 offset 0
   ```

   `EXISTS` never multiplies owner rows, so there's no `DISTINCT` and no duplicate owners, and `count(*)` for pagination is correct.
3. The query stays a builder until you run it, and `paginate()`, `cursorPaginate()` and `count()` all run in the database.

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
]);

$user->educations()->latestFirst()->get(); // current studies first, then most recent
```

- **Required fields.** Only `institution_name` is required, and the entry doesn't have to be a university degree.
- **Graduation year.** `end_date` is the source of truth when the exact date is known: each save derives `graduation_year` from it. If only the year is known, set `graduation_year` alone.
- **Ongoing studies.** Set `is_current`, and leave `end_date` empty (validation rejects it otherwise). `graduation_year` can hold the expected year.
- **Validation.** `end_date` must be on or after `start_date`. `graduation_year` must be between 1900 and the current year + 15, and no earlier than the start year.

## Certifications

```php
$user->certifications()->create([
    'name' => 'Professional Cloud Architect',     // required
    'issuing_organization' => 'Google',           // required, free text
    'issue_date' => '2024-03-01',
    'expiration_date' => '2026-03-01',            // null = does not expire
    'credential_id' => 'ABC-123',
    'credential_url' => 'https://example.com/verify/ABC-123',
]);

$certification->isExpired();               // compares with today, or a date you pass
$user->certifications()->valid()->get();   // not expired, including ones that never expire
$user->certifications()->expired()->get();
```

`credential_url` must use `http` or `https`. Credential IDs and URLs are stored as the owner entered them. **The package doesn't verify certifications.**

## Awards

```php
$user->awards()->create([
    'title' => 'Excellence Award',   // required
    'issuer' => 'Example Organization',
    'date_received' => '2023-05-01',
    'description' => 'For outstanding contributions.',
    'url' => 'https://example.com/awards/2023',
]);
```

Awards are stored separately from certifications.

## Database design, integrity and performance

### Tables

```text
Catalogs (shared, not polymorphic)          Profile data (polymorphic owner)
──────────────────────────────────          ─────────────────────────────────────────────
languages                         ◄──────── profile_languages (profileable_type, profileable_id, language_id, ...)
skill_categories ◄── skills       ◄──────── profile_skills    (profileable_type, profileable_id, skill_id, ...)
                     skills ◄── skill_aliases
                                            educations      (profileable_type, profileable_id, ...)
                                            certifications  (profileable_type, profileable_id, ...)
                                            awards          (profileable_type, profileable_id, ...)
```

Only the owner side is polymorphic. Languages, skills and aliases are ordinary shared tables.

### Constraints

| Constraint | Purpose |
| --- | --- |
| Primary key `(profileable_type, profileable_id, skill_id)` on `profile_skills` | An owner can't have the same skill twice. Owners of different types with the same ID stay independent. |
| Primary key `(profileable_type, profileable_id, language_id)` on `profile_languages` | The same, for languages. |
| `profile_*.language_id` / `skill_id` → catalog, **restrict** on delete | A catalog entry that a profile uses can't be deleted. Retire it with `is_active = false` instead. |
| `skill_aliases.skill_id` → `skills`, cascade | Aliases belong to their skill. |
| `skills.category_id` → `skill_categories`, set null | Categories only organize skills. |
| Unique normalized skill/language names, language codes and skill slugs | Canonical catalog entries can't be duplicated. |

The package can't add a foreign key from `profileable_id` to the owner, because the owner may live in any of several tables. Owner deletion is handled by the trait instead (see [Deleting owners](#deleting-owners)).

### Indexes and query patterns

| Index | Serves |
| --- | --- |
| `profile_skills` / `profile_languages` primary key `(type, id, catalog_id)` | Loading an owner's skills or languages, `hasSkill()`, and correlated `EXISTS` checks per owner |
| `(skill_id, type, id)` / `(language_id, type, id)` | Filters driven from the catalog side ("everyone with PHP"), catalog-side counts, and the catalog foreign key |
| `(type, id)` on `educations`, `certifications`, `awards` | Loading and deleting an owner's records |
| `skills.normalized_name`, `skill_aliases.normalized_alias`, `languages.code` / ISO codes / normalized names | Resolving terms to catalog IDs |

Owner filters compare integer catalog IDs, never text. Partial `LIKE` matching is only used by catalog search, never by owner filtering.

### Validation and transactions

Every package model validates itself before saving and throws `ValidationException`. The check is built into `save()`, so it still runs when events are faked. `rules()` can be reused in form requests.

Adding or updating a profile language or skill runs in a transaction. The steps are: check for a duplicate, clear the previous primary entry, then write. A concurrent duplicate insert becomes `DuplicateProfileEntry`.

### Customizing the migration

The migration is published into your application, so you can edit it. Turn off features you don't need under `features` **before** migrating. If you add columns, extend the matching model through the `models` config.

## Extending the package

### Custom models

```php
class Skill extends \Syriable\UserProfile\Models\Skill
{
    // extra relationships, scopes, casts ...
}

// config/user-profile.php
'models' => ['skill' => App\Models\Skill::class],
```

### Custom catalog search resolvers

Catalog search (and therefore term resolution) is handled by `SkillSearchResolver` and `LanguageSearchResolver`. Each takes an immutable criteria object and returns a query builder:

```php
use Illuminate\Database\Eloquent\Builder;
use Syriable\UserProfile\Search\DatabaseSkillSearchResolver;
use Syriable\UserProfile\Search\SkillSearchCriteria;

class PopularFirstSkillSearch extends DatabaseSkillSearchResolver
{
    public function search(SkillSearchCriteria $criteria): Builder
    {
        return parent::search($criteria)->reorder()
            ->withCount('profileSkills')
            ->orderByDesc('profile_skills_count')
            ->orderBy('name');
    }
}

// AppServiceProvider::boot()
UserProfile::registerSkillSearchResolver(PopularFirstSkillSearch::class);
```

A custom resolver must return each skill at most once and in a deterministic order. Term resolution uses the resolver in exact mode, so a resolver that widens exact matching also widens what writes and filters accept.

### Events

| Event | Properties |
| --- | --- |
| `SkillAdded` / `SkillUpdated` | `$owner`, `$skill`, `$pivot` |
| `SkillRemoved` | `$owner`, `$skill` |
| `LanguageAdded` / `LanguageUpdated` | `$owner`, `$language`, `$pivot` |
| `LanguageRemoved` | `$owner`, `$language` |

All events are in `Syriable\UserProfile\Events`. Education, certification and award records fire the usual Eloquent model events.

### Exceptions

Every package exception implements `Syriable\UserProfile\Exceptions\UserProfileException`:

| Exception | Thrown when |
| --- | --- |
| `DuplicateProfileEntry` | A language or skill is already on the profile. |
| `ProfileEntryNotFound` | A term, ID or entry isn't in the catalog, or isn't on the profile being updated. |
| `AmbiguousProfileEntry` | A write receives a term that names several catalog entries (see `$candidates`). |
| `InvalidProficiency` | A level isn't on the configured scale, or is required but missing. |
| `IncompatibleProfileOwner` | An owner model's key type doesn't match `owner_key_type`. |
| `FeatureDisabled` | A helper or filter for a disabled feature is called. |

## Security and privacy

- **No routes or authorization.** Use your own policies. `$record->isOwnedBy($owner)` compares both the morph type and the key:

  ```php
  public function update(User $user, Education $education): bool
  {
      return $education->isOwnedBy($user);
  }
  ```

- **The owner can't be mass assigned.** `profileable_type` and `profileable_id` aren't fillable, so create records through the owner's relationship: `$request->user()->educations()->create($validated)`.
- **Scope lookups to the owner.** Use `$user->educations()->findOrFail($id)`, not `Education::findOrFail($id)`. The pivot helpers only touch the calling owner's rows.
- **You decide visibility.** The package has no public-profile concept, so you choose what to expose.
- **Claims aren't verified.** Certifications, awards and skill levels are self-reported unless your application verifies them.
- **Safe input handling.** URLs must use `http` or `https`. Every search and filter value is passed as a query binding.

## Testing

```bash
composer test       # Pest
composer analyse    # PHPStan (level max, Larastan)
composer format     # Laravel Pint
composer refactor   # Rector
```

By default, the tests run on in-memory SQLite. Set `DB_CONNECTION` and the usual `DB_*` variables to run them against MySQL, MariaDB or PostgreSQL. CI runs the suite on PHP 8.4 and 8.5, Laravel 12 and 13, SQLite, MySQL 8.4, MariaDB 11 and PostgreSQL 17, with integer, UUID and ULID owners.

The package includes model factories:

```php
Skill::factory()->withAliases(['JS'])->create(['name' => 'JavaScript']);
Education::factory()->for($user, 'profileable')->current()->create();
```

## Troubleshooting

**`IncompatibleProfileOwner: … uses [int] primary keys, but user-profile.owner_key_type is [uuid]`**
Every owner model must use the configured key type. See [Primary key types](#primary-key-types).

**Profile rows remain after deleting owners**
The owners were deleted with a query builder delete, or with events faked. Call `$owner->deleteProfile()` for them.

**`AmbiguousProfileEntry` when adding a skill**
The term is an alias shared by several skills. Pass the intended `Skill` model or its ID. The exception's `$candidates` holds the options.

**`The model configured for [user-profile.models.skill] must extend …`**
A custom model must extend the package model it replaces.

**`FeatureDisabled`**
The feature is turned off under `features`, and its tables weren't migrated.

**Accented names clash on MySQL/MariaDB**
With `utf8mb4_unicode_ci`, `École` and `Ecole` compare as equal in unique indexes and exact matches.

## Architecture decisions and limitations

See [`docs/architecture.md`](docs/architecture.md).

## Changelog and upgrading

See [CHANGELOG](CHANGELOG.md) for what changed in each release, and [UPGRADING](UPGRADING.md) for upgrade steps. The package follows [Semantic Versioning](https://semver.org).

## License

The MIT License (MIT). See [License File](LICENSE.md).
