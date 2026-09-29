# Architecture decisions

This document explains why `syriable/user-profile` is built the way it is, and what it does not do.

## Naming

The package is `syriable/user-profile`, with the namespace `Syriable\UserProfile`, the facade `UserProfile`, the trait `HasUserProfile` and the config file `config/user-profile.php`. Earlier drafts of the specification used "UserCredentials" in some examples. One name is used throughout so the repository name, the namespace, the config key, the translation namespace and the publish tags all match.

## Package structure

```text
src/
├── Concerns/        HasUserProfile (user model), ValidatesAttributes, BelongsToProfileOwner
├── Contracts/       SkillSearchResolver, LanguageSearchResolver
├── Enums/           Feature, ProficiencyType
├── Events/          Skill/Language Added, Updated, Removed
├── Exceptions/      Typed exceptions + UserProfileException marker
├── Facades/         UserProfile
├── Models/          Language, UserLanguage, Skill, SkillAlias, SkillCategory, UserSkill,
│                    Education, Certification, Award
├── Search/          Criteria value objects + portable database resolvers
├── Support/         Normalizer, LanguageTag, ProficiencyScale, PackageConfig (internal),
│                    ProfileCatalog (internal), SlugGenerator (internal)
├── UserProfileManager.php
└── UserProfileServiceProvider.php
```

Each class has one job. The package has no repositories and no generic plugin system. The only interfaces are the two search contracts, because search is the one place where the specification asks for pluggable behaviour.

## Data model

### Reference catalogs and pivots

Languages and skills are catalogs, each with its own table. Users link to catalog rows through pivot tables (`user_languages`, `user_skills`). Data that belongs to the pairing of a user and a language or skill (proficiency, years of experience, native and primary flags) is stored on the pivot, so the catalog row stays free of user-specific data. Lists are never stored as JSON or comma-separated strings.

The pivots use a composite primary key (`user_id`, `language_id` / `skill_id`) instead of a surrogate ID. This prevents duplicate associations at the database level, and nothing ever needs to refer to a pivot row by its own ID.

Education, certifications and awards are one-to-many tables owned by the user. Institutions and issuers are free text. Adding an institutions catalog later would mean adding a nullable foreign key next to `institution_name`, not redesigning the table.

The package has no generic "profile data" table (entity-attribute-value). It would weaken typing, indexing and constraints, and none of the requirements need it.

### Normalized comparison columns

`skills.normalized_name`, `skill_aliases.normalized_alias`, `languages.normalized_name` and `languages.normalized_native_name` hold a canonical form of the text: Unicode NFKC normalization, whitespace trimmed and collapsed, then lowercased. The same function (`Support\Normalizer`) runs when these columns are written and when a search term is prepared. The alternatives are per-database collation rules or `LOWER()` in SQL (which on SQLite only lowercases ASCII). Normalizing in PHP instead gives:

- the same case- and whitespace-insensitive behaviour on SQLite, MySQL/MariaDB and PostgreSQL;
- unique indexes that reject `JavaScript` next to ` javascript `;
- plain B-tree indexes that exact matches can use.

The original spelling is always kept for display.

### Language identity

`code` holds a BCP 47 tag and is the canonical identifier, because many languages have no ISO 639-1 code (for example `yue`) and applications sometimes need regional or script variants (`zh-Hant`, `pt-BR`). ISO 639-1 and 639-3 codes are optional and unique when present. Tags are stored with conventional casing (`zh-Hant-TW`), so equivalent tags can't be stored twice.

### Deletion semantics

- Deleting a **user** cascades to their own records. That data belongs to the user and means nothing without them.
- Deleting a **catalog entry** that a profile uses is **restricted**. Deleting it would silently change users' profiles. Setting `is_active = false` retires an entry instead.
- Deleting a **skill** cascades to its **aliases**, which belong to the skill.
- Deleting a **category** sets `category_id` to null on its skills. Categories only organize skills.

### Configurable user model

The user foreign key column type is chosen from `user.key_type` (`int`, `uuid` or `ulid`). The referenced table and key name are read from the configured model rather than repeated in config.

## Proficiency

A proficiency scale (`Support\ProficiencyScale`) is an **ordered list of string keys**. A key's position in the list is its rank. Compared with other representations:

- storing integers would make stored data unreadable and tie it to one scale;
- storing labels would break when labels are translated or reworded;
- an enum would stop applications from choosing their own terms.

Keys are stable identifiers, and labels come from translation files, so changing a label never changes stored data. Ranking uses the list order, so sorting and "at least X" queries (`atLeast()`, `whereHasSkill($skill, 'advanced')`) need no extra columns.

"Native" is modelled as the `is_native` flag, not as a proficiency level. Nativeness and proficiency are different things, and a single field can't hold both without mixing them up.

CEFR is supported as its own scale, and CEFR labels are included. The package never maps CEFR levels to descriptive levels, because they are not exact equivalents.

A proficiency level is optional by default, to allow incomplete profiles. `require_proficiency` and `default_proficiency` let applications be stricter. Proficiency is never inferred from years of experience.

## Search

### Why SQL with indexes

The specification rules out external search services and asks for portable SQL. `LIKE '%term%'` can't use a B-tree index, but it is portable, predictable and fast enough for a skill catalog of tens of thousands of rows. Exact lookups (`partial: false`) use the indexes on the normalized columns. Full-text search (`MATCH … AGAINST`, `tsvector`) was considered and rejected: it behaves differently on each database, SQLite has no equivalent without extensions, and its tokenization handles short technical terms such as "C#", "JS" or "UI" poorly.

### Correctness properties

- **No duplicate results.** Alias conditions are `EXISTS` subqueries, not joins, so each skill appears at most once. That makes `paginate()` and `count()` correct.
- **Deterministic order.** Results are ranked by exact name, exact alias, name prefix, alias prefix, then other matches, with name and primary key as final tie-breakers. Every sort key is part of the query, so pages are stable.
- **Literal wildcards.** `%` and `_` in a search term are escaped. `!` is used as the escape character because it needs no special quoting on any supported database.
- **Only literal SQL.** Raw SQL fragments are fixed literal strings (Larastan checks this). Every search value is a binding.
- **Ambiguity is surfaced.** An alias can belong to several skills, and every matching skill is returned. The package never chooses between them.
- **Short terms match exactly.** Terms shorter than `min_partial_length` match exactly, so a one-letter autocomplete query doesn't return the whole catalog.

### Why the search API returns a query builder

`UserProfile::searchSkills()` returns `Builder<Skill>` instead of a collection or a paginator. Callers can then use `get()`, `paginate()`, `cursorPaginate()`, `limit()`, extra `where` clauses or eager loading, and the package doesn't need a separate method for each. Search options are named arguments that are turned into an immutable criteria object (`SkillSearchCriteria`) before the resolver receives them.

### Extension point

`SkillSearchResolver` and `LanguageSearchResolver` each have one method, criteria in and query builder out. The database resolvers are open classes, so applications can extend them (for example, to rank by popularity) rather than rewrite them. `UserProfile::register*SearchResolver()` is a thin wrapper around container binding.

## Integrity and validation

- Every package model validates itself with its public `rules()` before `save()`. This is built into `save()` instead of the `saving` event, so validation and derived attributes (slugs, `graduation_year`) still work when an application calls `Event::fake()` in its tests. Applications can reuse `rules()` in form requests.
- Unique rules are bound to the model's own connection and table, so they keep working with custom model subclasses and non-default connections.
- The pivot helpers (`addSkill()`, `updateSkill()` and so on) accept a whitelist of attributes, check their types strictly, and run in a transaction. The steps are: check for a duplicate, clear any previous primary entry, then write. A unique constraint violation caused by a concurrent insert becomes `DuplicateProfileEntry`.
- `graduation_year` has one source of truth. It is derived from `end_date` whenever an end date exists, and can be stored alone when only the year is known.

## Security

- The package defines no routes and does no authorization. Applications use their own policies. `isOwnedBy()` helps with that.
- `user_id` is never mass assignable. Owner records are created through the owner's relationship.
- Credential and award URLs must use `http` or `https`.
- Credentials are stored as user claims. The package has no "verified" state, so it can't be mistaken for verification.

## Known limitations

- **Partial search scans the table.** `LIKE '%term%'` can't use an index. For very large catalogs (hundreds of thousands of skills), register a custom resolver that uses your database's full-text or trigram features (for example PostgreSQL `pg_trgm`).
- **No fuzzy matching or typo tolerance.** "Javscript" doesn't match "JavaScript". Add aliases for common misspellings, or use a custom resolver.
- **Collation affects accent sensitivity on MySQL/MariaDB.** With `utf8mb4_unicode_ci`, `École` and `Ecole` compare as equal in unique indexes and exact matches. PostgreSQL and SQLite treat them as different.
- **Primary flags are enforced by the application, not the database.** "One primary language/skill per user" is kept by the helpers inside a transaction. Two concurrent requests could still both set a primary entry. Writing to the pivot directly bypasses the rule.
- **Changing scales doesn't migrate data.** If you switch proficiency scales, existing stored levels are not converted.
- **Translations** are provided in English only. Other locales can be added by publishing the translation files.
- **Feature toggles apply at migration time.** Turning a feature on after migrating needs a new migration for its tables.
- **No soft deletes, ordering columns or visibility flags.** These are application concerns. Add them through custom models and migrations if you need them.
