# Changelog

All notable changes to `syriable/user-profile` will be documented in this file.

## Unreleased

Initial release.

### Polymorphic ownership and owner filtering

Profile data now belongs to any Eloquent model using `HasUserProfile`, through native polymorphic relations, and owners can be filtered by their skills and languages in the database.

- Added owner filter scopes: `whereSkill`, `whereAnySkill`, `whereAllSkills`, `withoutSkill`, `whereLanguage`, `whereAnyLanguage`, `whereAllLanguages` and `withoutLanguage`, with `atLeast:` proficiency thresholds and `native:` / `primary:` flags.
- Added `UserProfile::resolveSkills()` / `resolveLanguages()`. Skills and languages can now be referenced by name, alias, slug or code.
- Added `AmbiguousProfileEntry` for writes through ambiguous aliases, and `IncompatibleProfileOwner` for owner models whose key type doesn't match the configuration.
- Added `profileSkills()` / `profileLanguages()` on owners and on the catalog models, `profileable()` on owned records, and `deleteProfile()`.
- Owner deletion now removes the owner's profile data (force delete for soft-deleting owners).

Breaking changes compared with the earlier development schema:

| Before | After |
| --- | --- |
| `user.model`, `user.key_type` config | `owner_key_type` |
| `user_languages`, `user_skills` tables | `profile_languages`, `profile_skills` |
| `user_id` columns with a foreign key | `profileable_type` + `profileable_id`, cleaned up by the trait |
| `UserLanguage`, `UserSkill` models | `ProfileLanguage`, `ProfileSkill` |
| `Language::users()`, `Skill::users()` | `Language::profileLanguages()`, `Skill::profileSkills()` |
| `$record->user()` | `$record->profileable()` |
| `whereHasSkill()`, `whereHasLanguage()` | `whereSkill()`, `whereLanguage()` (`atLeast:` argument) |
| Event property `$user` | `$owner` |
| String identifiers: skill slug / language code only | name, alias, slug or code (writes must be unambiguous) |

If you ran the earlier migration, for each of `user_languages`, `user_skills`, `educations`, `certifications` and `awards`:

1. Drop the `user_id` foreign key.
2. Add `profileable_type` and fill it with your user model's morph class.
3. Rename `user_id` to `profileable_id`.
4. Rebuild the primary key and indexes to match the new migration.
5. Rename `user_languages` / `user_skills` to `profile_languages` / `profile_skills`.

- `HasUserProfile` trait for any Eloquent user model (int, UUID or ULID keys).
- Language catalog with BCP 47 / ISO 639-1 / ISO 639-3 codes and a `user_languages` pivot (proficiency, native and primary flags).
- Skill catalog with optional categories, aliases and a `user_skills` pivot (proficiency, years of experience, primary flag).
- Configurable, ordered proficiency scales for languages and skills, with translatable labels and CEFR labels included.
- Alias-aware, portable SQL search for skills and languages with deterministic ranking and pagination.
- Pluggable search resolvers via `UserProfile::registerSkillSearchResolver()` / `registerLanguageSearchResolver()`.
- Education history, certifications and awards with model-level validation.
- Feature toggles, configurable table names and model classes.
- Events for adding, updating and removing profile languages and skills.
- Starter seeders for languages and skills.
