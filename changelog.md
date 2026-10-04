# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/).

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
