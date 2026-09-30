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

namespace tool_wizards;

/**
 * Event observers: clean-up of this plugin's rows when courses or users go away.
 */
class observer {
    /**
     * Forget per-course dismissals for a deleted course.
     *
     * @param \core\event\course_deleted $event the event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records('tool_wizards_dismissed', ['courseid' => $event->objectid]);
    }

    /**
     * Remove a deleted user's rows.
     *
     * @param \core\event\user_deleted $event the event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('tool_wizards_dismissed', ['userid' => $event->objectid]);
        $DB->delete_records('tool_wizards_message', ['userid' => $event->objectid]);
    }
}
