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

namespace tool_wizards\local;

use tool_wizards\local\wizard\extensions;

/**
 * Adds content to an existing activity from an in-activity wizard's answers, through the
 * wizard's content handler ({@see \tool_wizards\local\wizard\content}).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_creator {
    /**
     * The handler of an in-activity wizard.
     *
     * @param array $doc the definition
     * @return string the handler class
     * @throws \moodle_exception when it has none (not an in-activity wizard, or its plugin is gone)
     */
    public static function handler(array $doc): string {
        $class = extensions::content_for($doc);
        if (!$class) {
            throw new \moodle_exception('error_nowizard', 'tool_wizards');
        }
        return $class;
    }

    /**
     * Whether the current user may use an in-activity wizard on an activity: it is for this kind
     * of activity, the handler can be used there, and the user may manage the activity and has
     * the handler's own capabilities.
     *
     * @param array $doc the definition
     * @param \cm_info $cm the activity
     * @return bool
     */
    public static function may_use(array $doc, \cm_info $cm): bool {
        $class = extensions::content_for($doc);
        if (!$class || $class::modname() !== $cm->modname || ($doc['target']['modname'] ?? '') !== $cm->modname) {
            return false;
        }
        $context = $cm->context;
        if (!has_capability('moodle/course:manageactivities', $context)) {
            return false;
        }
        foreach ($class::capabilities() as $capability) {
            if (!has_capability($capability, $context)) {
                return false;
            }
        }
        return $class::is_available($cm);
    }

    /**
     * Check the fields without saving.
     *
     * @param array $doc the definition
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(array $doc, \cm_info $cm, array $fields): array {
        return self::handler($doc)::check($cm, $fields);
    }

    /**
     * Save the content.
     *
     * @param array $doc the definition
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return string what was added, for the teacher
     * @throws \moodle_exception when the handler refuses the fields
     */
    public static function save(array $doc, \cm_info $cm, array $fields): string {
        $errors = self::check($doc, $cm, $fields);
        if ($errors) {
            throw new \moodle_exception('error_moduleform', 'tool_wizards', '', null, json_encode($errors));
        }
        return self::handler($doc)::save($cm, $fields);
    }
}
