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
 * Suggest a free short name for a course the user is about to create.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggest_shortname extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fullname' => new external_value(PARAM_TEXT, 'The course full name'),
        ]);
    }

    /**
     * Suggest a short name.
     *
     * @param string $fullname the course full name
     * @return array ['shortname' => string]
     */
    public static function execute(string $fullname): array {
        ['fullname' => $fullname] = self::validate_parameters(self::execute_parameters(), ['fullname' => $fullname]);
        self::validate_context(\core\context\system::instance());
        self::require_course_creator();
        return ['shortname' => course_creator::suggest_shortname($fullname)];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'shortname' => new external_value(PARAM_TEXT, 'A short name no course uses yet'),
        ]);
    }

    /**
     * Only people who can create a course somewhere may use this.
     *
     * @throws \required_capability_exception
     */
    public static function require_course_creator(): void {
        if (!course_creator::get_categories()) {
            throw new \required_capability_exception(\core\context\system::instance(), 'moodle/course:create', 'nopermissions', '');
        }
    }
}
