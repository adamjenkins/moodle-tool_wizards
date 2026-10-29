# Wizards 0.3.3

Fixes for an external security review of 0.3.2.

## Security

- A wizard's `"config:plugin/name"` defaults (and `percentof` values) only read course and activity
  defaults. Values set in config.php, such as the database password, and settings named like a
  password, secret, key, token or salt are never read, and wizard definitions that use them are refused.
- Question labels, yes/no texts and list options from a wizard definition are now always shown as
  plain text.
- The "Manage wizards" capability is marked as an XSS risk as well as a configuration risk, because
  wizards are shown to teachers.

## Fixed

- The course wizard no longer changes a course setting that Moodle locks for the person creating the
  course, such as "Enable download course content" without the capability for it.

## Changed

- Importing a wizard in Replace mode leaves it as a draft, like every other import, so it can be tried
  before teachers see it.
- The messages of the wizard validator, the import page and the JSON editor can now be translated.
