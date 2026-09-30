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

/**
 * Web service functions for tool_wizards (used by its own JavaScript only).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_wizards_suggest_shortname' => [
        'classname' => \tool_wizards\external\suggest_shortname::class,
        'description' => 'Suggest a free course short name from a full name.',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_wizards_check_shortname' => [
        'classname' => \tool_wizards\external\check_shortname::class,
        'description' => 'Check whether a course short name is free.',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_wizards_dismiss_course' => [
        'classname' => \tool_wizards\external\dismiss_course::class,
        'description' => 'Hide the first-content suggestions on one course for the current user.',
        'type' => 'write',
        'ajax' => true,
    ],
];
