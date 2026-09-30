# Changelog

All notable changes to `syriable/user-profile` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-29

First stable release.

### Requirements

- PHP 8.4+
- Laravel 12.x or 13.x
- SQLite, MySQL 8+, MariaDB 10.11+ or PostgreSQL 14+

### Added

#### Profile owners

- `HasUserProfile` trait that turns any Eloquent model (for example `User`, `Seller` or `Company`) into a profile owner. It uses native polymorphic relations on `profileable_type` / `profileable_id`.
- Relationships: `languages()`, `skills()`, `educations()`, `certifications()`, `awards()`, plus the pivot-row relations `profileLanguages()` and `profileSkills()`.
- Integer, UUID and ULID owner keys, selected with the `owner_key_type` option. Owner models whose key type doesn't match throw `IncompatibleProfileOwner`.
- Compatibility with application morph maps. The package registers none of its own.
- Automatic cleanup of an owner's profile when the owner is deleted, or force deleted for soft-deleting models. `deleteProfile()` is available for query-builder deletes.

#### Languages and skills

- Shared language catalog with BCP 47 codes, optional ISO 639-1 / ISO 639-3 codes, native names and an active flag.
- Shared skill catalog with generated slugs, optional categories, and aliases that can be scoped to a locale.
- `addLanguage()`, `updateLanguage()`, `removeLanguage()` and `hasLanguage()`, with proficiency, native and primary flags.
- `addSkill()`, `updateSkill()`, `removeSkill()` and `hasSkill()`, with proficiency, years of experience and a primary flag.
- `UserProfile::resolveSkills()` / `resolveLanguages()`, so skills and languages can be referenced by name, alias, slug or code.
- `AmbiguousProfileEntry`, thrown when a write uses a term that names several catalog entries. The package never picks one on its own.

#### Proficiency

- Configurable, ordered proficiency scales for languages and skills (`UserProfile::languageProficiency()` / `skillProficiency()`), with ranking, comparison and `atLeast()` thresholds.
- Translatable labels, including CEFR (A1–C2) labels.
- Native speakers are marked with a separate `is_native` flag, not a proficiency level.

#### Searching and filtering

- Catalog search: `UserProfile::searchSkills()` and `searchLanguages()` return query builders. Search is alias-aware and case- and whitespace-insensitive, supports exact and partial matching, ranks results in a fixed order and paginates in the database.
- Owner filter scopes: `whereSkill()`, `whereAnySkill()`, `whereAllSkills()` and `withoutSkill()`, plus `whereLanguage()`, `whereAnyLanguage()`, `whereAllLanguages()` and `withoutLanguage()`. They accept `atLeast:`, `native:` and `primary:` options, run as `EXISTS` queries in the database, and never return duplicate owners.
- Pluggable catalog search through `UserProfile::registerSkillSearchResolver()` / `registerLanguageSearchResolver()`.

#### Education, certifications and awards

- Education history with a single source of truth for completion dates, ongoing studies and an application-defined `type`.
- Certifications with optional credential IDs and verification URLs, expiration handling, and `valid()` / `expired()` scopes.
- Awards with optional issuer, date, description and link.
- Validation on every package model before saving. Each model's `rules()` can be reused in form requests.

#### Tooling

- Events: `SkillAdded`, `SkillUpdated`, `SkillRemoved`, `LanguageAdded`, `LanguageUpdated` and `LanguageRemoved`.
- Feature toggles, configurable table names and replaceable model classes.
- `user-profile:install` command, publishable config, migration and translations.
- Starter `LanguageSeeder` and `SkillSeeder`, and model factories.

[Unreleased]: https://github.com/syriable/user-profile/compare/1.0.0...HEAD
[1.0.0]: https://github.com/syriable/user-profile/releases/tag/1.0.0
