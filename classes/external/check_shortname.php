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

namespace tool_wizards\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_wizards\local\course_creator;

/**
 * Check whether a short name is still free, and suggest one that is if not.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_shortname extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'shortname' => new external_value(PARAM_TEXT, 'The short name to check'),
        ]);
    }

    /**
     * Check a short name.
     *
     * @param string $shortname the short name
     * @return array ['available' => bool, 'suggestion' => string]
     */
    public static function execute(string $shortname): array {
        global $DB;
        ['shortname' => $shortname] = self::validate_parameters(self::execute_parameters(), ['shortname' => $shortname]);
        self::validate_context(\core\context\system::instance());
        suggest_shortname::require_course_creator();

        $shortname = trim($shortname);
        $available = !$DB->record_exists('course', ['shortname' => $shortname]);
        return [
            'available' => $available,
            'suggestion' => $available ? $shortname : course_creator::make_unique_shortname($shortname),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'available' => new external_value(PARAM_BOOL, 'Whether no course uses this short name yet'),
            'suggestion' => new external_value(PARAM_TEXT, 'A free short name based on it'),
        ]);
    }
}
