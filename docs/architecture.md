# Architecture decisions

This document explains why `syriable/user-profile` is built the way it is, and what it does not do.

## Naming

The package is `syriable/user-profile`, with the namespace `Syriable\UserProfile`, the facade `UserProfile`, the trait `HasUserProfile` and the config file `config/user-profile.php`. Earlier drafts of the specification used "UserCredentials" in some examples. One name is used throughout so the repository name, the namespace, the config key, the translation namespace and the publish tags all match.

## Package structure

```text
src/
├── Concerns/        HasUserProfile + FiltersProfileOwners (owner models), BelongsToProfileOwner,
│                    ValidatesAttributes
├── Contracts/       SkillSearchResolver, LanguageSearchResolver
├── Enums/           Feature, ProficiencyType
├── Events/          Skill/Language Added, Updated, Removed
├── Exceptions/      Typed exceptions + UserProfileException marker
├── Facades/         UserProfile
├── Models/          Language, ProfileLanguage, Skill, SkillAlias, SkillCategory, ProfileSkill,
│                    Education, Certification, Award
├── Search/          Criteria value objects + portable database resolvers
├── Support/         Normalizer, LanguageTag, ProficiencyScale, PackageConfig (internal),
│                    ProfileCatalog, ProfileOwner, SlugGenerator (internal)
├── UserProfileManager.php
└── UserProfileServiceProvider.php
```

Each class has one job. The package has no repositories and no generic plugin system. The only interfaces are the two search contracts, because search is the one place where the specification asks for pluggable behaviour.

## Data model

### Reference catalogs and pivots

Languages and skills are catalogs, each with its own table, and they are not polymorphic. Owners link to catalog rows through pivot tables (`profile_languages`, `profile_skills`). Data that belongs to the pairing of an owner and a language or skill (proficiency, years of experience, native and primary flags) is stored on the pivot, so the catalog row stays free of owner-specific data. Lists are never stored as JSON or comma-separated strings.

The pivots use a composite primary key (`profileable_type`, `profileable_id`, `language_id` / `skill_id`) instead of a surrogate ID. This prevents duplicate associations at the database level, and the owner type is part of the boundary, so owners of different types that share an ID stay independent.

Education, certifications and awards are one-to-many tables owned by the profile owner. Institutions and issuers are free text. Adding an institutions catalog later would mean adding a nullable foreign key next to `institution_name`, not redesigning the table.

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

- Deleting an **owner** deletes its profile rows. A polymorphic column can't have a foreign key, so this is done by a `deleted` listener in `HasUserProfile`, inside a transaction. Soft-deleted owners keep their profile until they are force deleted. Query builder deletes fire no events, so `deleteProfile()` is public for those cases.
- Deleting a **catalog entry** that a profile uses is **restricted**. Deleting it would silently change owners' profiles. Setting `is_active = false` retires an entry instead.
- Deleting a **skill** cascades to its **aliases**, which belong to the skill.
- Deleting a **category** sets `category_id` to null on its skills. Categories only organize skills.

### Polymorphic ownership

Profile data belongs to its owner through Laravel's native polymorphic relations: `morphToMany` for skills and languages (with `MorphPivot` models), and `morphMany` for education, certifications and awards. Any Eloquent model becomes an owner by using `HasUserProfile`, with no base class or registration. The stored type is `getMorphClass()`, so application morph maps are respected, and the package never registers its own.

The migration creates `profileable_type` and `profileable_id` explicitly rather than with `morphs()`. `morphs()` follows the application's global `Schema::morphUsingUuids()` / `morphUsingUlids()` default, and the package's column type must not change because of an unrelated global setting.

### One owner key type per installation

A column has one native type, so every owner model shares the key type set in `owner_key_type` (`int` → `unsigned bigint`, `uuid` → `uuid`, `ulid` → `char(26)`). A `varchar` column holding mixed integer and string keys was evaluated and rejected, based on these results:

- PostgreSQL has no `varchar = bigint` operator. Every correlated `whereHas` (`users.id = profile_skills.profileable_id`) fails, and so does every eager load of integer-keyed owners, because Laravel inlines integer keys as `IN (1, 2, ...)`.
- MySQL/MariaDB accept the comparison but convert each `varchar` value to a number, which turns an index range scan into a full index scan (verified with `EXPLAIN`).

Supporting mixed keys would therefore be broken on one database and slow on another. `Support\ProfileOwner` enforces the contract instead: the first profile relationship called on an owner whose key type (or key value) doesn't match the configuration throws `IncompatibleProfileOwner`. The class-level check is cached, so this adds no measurable cost.

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

## Owner filtering

Owner filtering is implemented as local scopes on the owner model (`whereSkill`, `whereAnySkill`, `whereAllSkills`, `withoutSkill` and the matching language scopes), in `Concerns\FiltersProfileOwners`. Scopes keep the API small and native: they return the owner's own `Builder`, they compose with `where`/`orWhere` closures for grouping, and they add no query-builder class or search service. A separate `ProfileQuery::for(User::class)` entry point was considered and rejected, because it would be a second way to build the same Eloquent query.

- **Resolve, then filter.** A term is first resolved to catalog IDs with one indexed query (`UserProfile::resolveSkills()` / `resolveLanguages()`), which reuses the registered catalog search resolver in exact mode. The owner query then filters by integer IDs only, so no text matching runs per owner.
- **`EXISTS` on the pivot rows.** Each scope is one `whereHas` / `whereDoesntHave` on the `profileSkills` / `profileLanguages` `morphMany` relations. This produces `EXISTS (select … from profile_skills where profileable_id = owner.id and profileable_type = ? and skill_id in (…))`. It doesn't join the catalog, it can't duplicate owners (so `DISTINCT` isn't needed), and the morph type is always part of the match.
- **Canonical proficiency.** `atLeast:` becomes `proficiency_level IN (levels at or above)`, computed by `ProficiencyScale::atLeast()`. Proficiency is defined in one place and never compared as strings. For languages, native speakers always satisfy a threshold.
- **Semantics.** Chaining means AND. `whereAny*` means OR within one list, and `whereAll*` means AND across a list. `without*` means NOT. An unknown term resolves to no IDs, so `where*` matches nobody and `without*` excludes nobody. Empty lists follow `whereIn([])`: "any" matches nobody and "all" adds no condition.
- **Ambiguous aliases.** For reads and filters, a term that names several catalog entries stands for all of them. Writes (`addSkill`, `updateSkill`, `removeSkill` and the language equivalents) require exactly one match and otherwise throw `AmbiguousProfileEntry` with the candidates. The package never picks one meaning on its own.

## Integrity and validation

- Every package model validates itself with its public `rules()` before `save()`. This is built into `save()` instead of the `saving` event, so validation and derived attributes (slugs, `graduation_year`) still work when an application calls `Event::fake()` in its tests. Applications can reuse `rules()` in form requests.
- Unique rules are bound to the model's own connection and table, so they keep working with custom model subclasses and non-default connections.
- The pivot helpers (`addSkill()`, `updateSkill()` and so on) accept a whitelist of attributes, check their types strictly, and run in a transaction. The steps are: check for a duplicate, clear any previous primary entry, then write. A unique constraint violation caused by a concurrent insert becomes `DuplicateProfileEntry`.
- `graduation_year` has one source of truth. It is derived from `end_date` whenever an end date exists, and can be stored alone when only the year is known.

## Security

- The package defines no routes and does no authorization. Applications use their own policies. `isOwnedBy()` helps with that.
- `profileable_type` and `profileable_id` are never mass assignable. Owner records are created through the owner's relationship.
- Credential and award URLs must use `http` or `https`.
- Credentials are stored as owner claims. The package has no "verified" state, so it can't be mistaken for verification.

## Known limitations

- **One owner key type per installation.** Integer, UUID and ULID owners can't be mixed; see [One owner key type per installation](#one-owner-key-type-per-installation).
- **No database-level cascade to owners.** Profile rows are removed by an Eloquent listener. Query builder deletes, raw SQL deletes and faked events bypass it, which can leave orphaned rows. Call `deleteProfile()` in those cases.
- **One resolution query per term.** `whereAllSkills(['a', 'b', 'c'])` with string terms runs three small indexed catalog queries before the owner query. Passing models or IDs avoids them.

- **Partial search scans the table.** `LIKE '%term%'` can't use an index. For very large catalogs (hundreds of thousands of skills), register a custom resolver that uses your database's full-text or trigram features (for example PostgreSQL `pg_trgm`).
- **No fuzzy matching or typo tolerance.** "Javscript" doesn't match "JavaScript". Add aliases for common misspellings, or use a custom resolver.
- **Collation affects accent sensitivity on MySQL/MariaDB.** With `utf8mb4_unicode_ci`, `École` and `Ecole` compare as equal in unique indexes and exact matches. PostgreSQL and SQLite treat them as different.
- **Primary flags are enforced by the application, not the database.** "One primary language/skill per owner" is kept by the helpers inside a transaction. Two concurrent requests could still both set a primary entry. Writing to the pivot directly bypasses the rule.
- **Changing scales doesn't migrate data.** If you switch proficiency scales, existing stored levels are not converted.
- **Translations** are provided in English only. Other locales can be added by publishing the translation files.
- **Feature toggles apply at migration time.** Turning a feature on after migrating needs a new migration for its tables.
- **No soft deletes, ordering columns or visibility flags.** These are application concerns. Add them through custom models and migrations if you need them.
