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

/**
 * The first-content suggestions shown at the top of a course page.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt {
    /**
     * Remember that the current user has just created this course with the wizard,
     * so its course page opens with "Your course is ready".
     *
     * @param int $courseid the new course
     */
    public static function mark_just_created(int $courseid): void {
        global $SESSION;
        $SESSION->tool_wizards_justcreated = $courseid;
    }

    /**
     * Hide the suggestions on a course for the current user.
     *
     * @param int $courseid the course
     */
    public static function dismiss_course(int $courseid): void {
        global $DB, $USER;
        if (!$DB->record_exists('tool_wizards_dismissed', ['userid' => $USER->id, 'courseid' => $courseid])) {
            $DB->insert_record('tool_wizards_dismissed', (object) [
                'userid' => $USER->id,
                'courseid' => $courseid,
                'timecreated' => time(),
            ]);
        }
    }

    /**
     * The HTML to add at the top of a course page, if any.
     *
     * @param \moodle_page $page the course page
     * @param \renderer_base $renderer the page renderer
     * @return string
     */
    public static function render_for_page(\moodle_page $page, \renderer_base $renderer): string {
        return '';
    }
}
