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
 * Event observers for tool_wizards.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\tool_wizards\observer::course_deleted',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\tool_wizards\observer::user_deleted',
    ],
    // Teacher scaffold (tool_teacherscaffold) is optional. Core stores an observer's event name as
    // a plain string and only looks it up when an event of that class is triggered, so this entry
    // is inert, and silent, when that plugin is not installed. The contract is in
    // dev-docs/new-moodle-user-help/RELATIONS.md; after the teacher's own transaction commits.
    [
        'eventname' => '\tool_teacherscaffold\event\tier_unlocked',
        'callback' => '\tool_wizards\observer::tier_unlocked',
        'internal' => false,
    ],
];
