<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_wizards\local\wizard;

/**
 * A content handler: what an in-activity wizard adds to an existing activity (a question, a
 * lesson page, a book chapter, ...), and how it is saved.
 *
 * A wizard with the target {"type": "content", "modname": "lesson", "content": "<name>"} maps its
 * answers to the handler's fields, as an activity wizard maps them to the activity form's fields.
 * The handler checks them and saves them through the activity's own API, so events, files, the
 * gradebook and backup behave as when the teacher uses the activity's own pages.
 *
 * Handlers are registered with {@see \tool_wizards\hook\collect_extensions::add_content()}.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class content {
    /**
     * The activity module this content belongs to.
     *
     * @return string module name, e.g. "lesson"
     */
    abstract public static function modname(): string;

    /**
     * The fields the wizard's answers may set, for the validator and the runbook.
     *
     * @return array field name (without any [..] part) => what it is, in a few words of English
     */
    abstract public static function fields(): array;

    /**
     * The page types where "Add with a wizard" appears for this content, e.g. "mod-lesson-edit".
     *
     * @return string[]
     */
    abstract public static function pagetypes(): array;

    /**
     * Save the content.
     *
     * Called only after {@see check()} found nothing wrong.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value, nested like $_POST
     * @return string what was added, for the teacher, e.g. "Page: Welcome"
     */
    abstract public static function save(\cm_info $cm, array $fields): string;

    /**
     * Capabilities the user needs in the activity's context, besides moodle/course:manageactivities.
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return [];
    }

    /**
     * Whether the wizard can be used on this activity now.
     *
     * @param \cm_info $cm the activity
     * @return bool
     */
    public static function is_available(\cm_info $cm): bool {
        return true;
    }

    /**
     * The values this activity offers for choice fields, for "offered" conditions.
     *
     * @param \cm_info $cm the activity
     * @return array field => string[]
     */
    public static function offered(\cm_info $cm): array {
        return [];
    }

    /**
     * Check the fields before anything is saved.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        return [];
    }

    /**
     * Whether the teacher is offered "Add another" after saving.
     *
     * @return bool
     */
    public static function repeatable(): bool {
        return true;
    }

    /**
     * Where the teacher sees the result.
     *
     * @param \cm_info $cm the activity
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return $cm->url;
    }
}
