# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Wizards are definitions that site administrators manage in *Site administration > Courses >
  Wizards > Manage wizards*: enable or disable each, set their order, edit their wording in every
  installed language, choose or upload card pictures, duplicate, delete, and export or import them
  (one or many, as a zip with their pictures), with bulk actions.
- A wizard for building wizards: choose what it creates, pick that form's own settings, word the
  questions and group them into screens, and add purpose cards with presets.
- "Try it": walk any wizard in a real course (or the course wizard) and see what it would set,
  without creating anything.
- "Improve with AI" in the wizard editor, through Moodle's AI subsystem when a text provider is
  set up; the proposed changes are shown before anything is saved.
- An AI runbook, a JSON Schema and a command-line validator (`cli/validate.php`) for improving
  exported wizards with any AI assistant.
- "Add with a wizard", with a magic wand icon, in every course section's add menu in edit mode. It
  opens a list of the wizards with each activity's icon, coloured by its purpose as in Moodle's
  activity chooser, and a line saying what each one helps with.
- Plugins can add their own actions and transforms through the `\tool_wizards\hook\collect_extensions` hook.
- The default wizards update with the plugin unless edited; edited ones are flagged when a newer
  default ships, with a comparison, "Reset to default" and "Keep my version".

### Changed

- "Bring the wizards back" in the footer's help menu is now "Bring back the course wizards". It
  appears whenever the wizards were hidden on that course or switched off everywhere, also on a
  course with content, and shows the card again straight away.
- The course wizard and the seven content wizards are now default definitions run by one engine.
  Their questions and behaviour are unchanged.

## [0.1.2] - 2026-10-04

### Changed

- Maturity is now `MATURITY_BETA` (was `MATURITY_ALPHA`).
- `composer.json`: `moodle/moodle` constraint is now `^5.2` (was `>=5.2 <5.4`),
  so later 5.x releases are not excluded.
- CI now also tests MOODLE_503_STABLE (PHP 8.3-8.4, PostgreSQL 17, MariaDB 11.4).

## [0.1.1] - 2026-10-04

### Added

- Support for Moodle 5.2 and 5.3.
- composer.json, so the plugin can be installed with Composer.
- Course wizard: create a course by answering one question at a time, with a review screen
  and a "Show all settings" route to the standard course form that keeps the answers.
- First-content suggestions with mini-wizards for a file, slides, a picture, a page, a
  forum, a glossary and a quiz (with an optional first multiple choice or true/false question).
- Stepped mini-wizards: essentials first, then one theme per screen, with purpose cards whose
  presets pre-fill the later screens, and "Enough questions, let's go" to stop early. Shared
  screens for visibility, groups and completion.
- Per-course and site-wide dismissal of the suggestions, reversible from Preferences, and
  per course from "Bring the wizards back" in the footer's help menu.
- Messages about activities unlocked in Teacher scaffold, when that plugin is installed.
- Privacy provider for the preference, the dismissed courses and the queued messages.
