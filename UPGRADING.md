# Upgrading

## From development builds to 1.0.0

This only applies if you installed `syriable/user-profile` from the `main` branch before 1.0.0 was tagged **and** ran its migration. Those development builds tied profile data to a single user model with `user_id` columns. Since 1.0.0, profile data belongs to any model through polymorphic `profileable_type` / `profileable_id` columns.

### Code changes

| Development builds | 1.0.0 |
| --- | --- |
| `user.model`, `user.key_type` config | `owner_key_type` |
| `UserLanguage`, `UserSkill` models | `ProfileLanguage`, `ProfileSkill` |
| `Language::users()`, `Skill::users()` | `Language::profileLanguages()`, `Skill::profileSkills()` |
| `$record->user()` | `$record->profileable()` |
| `whereHasSkill($skill, $level)`, `whereHasLanguage($language, $level)` | `whereSkill($skill, atLeast: $level)`, `whereLanguage($language, atLeast: $level)` |
| Event property `$user` | `$owner` |
| Strings: skill slug / language code only | name, alias, slug or code; writes must be unambiguous |

Republish the config file (`php artisan vendor:publish --tag="user-profile-config" --force`), or replace the `user` block with `'owner_key_type' => 'int'` (or `uuid` / `ulid`).

### Database changes

| Development builds | 1.0.0 |
| --- | --- |
| `user_languages`, `user_skills` tables | `profile_languages`, `profile_skills` |
| `user_id` column with a foreign key | `profileable_type` + `profileable_id`, no foreign key |
| Primary key `(user_id, …)` | Primary key `(profileable_type, profileable_id, …)` |

For each of `user_languages`, `user_skills`, `educations`, `certifications` and `awards`:

1. Drop the `user_id` foreign key.
2. Add `profileable_type` and fill it with your user model's morph class (`(new User)->getMorphClass()`).
3. Rename `user_id` to `profileable_id`.
4. Rebuild the primary key and indexes to match the published 1.0.0 migration.
5. Rename `user_languages` / `user_skills` to `profile_languages` / `profile_skills`.

On a database with no data worth keeping, it's simpler to roll back the old migration and run the new one.
