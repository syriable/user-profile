# Changelog

All notable changes to `syriable/user-profile` will be documented in this file.

## Unreleased

Initial release.

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
