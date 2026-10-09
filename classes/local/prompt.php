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

use stdClass;

/**
 * The first-content suggestions ("What would you like to add first?") shown at the
 * top of a course page, and the messages that share their place.
 *
 * The suggestions appear only to people who can add content to the course, only on
 * a course with little or no content (or straight after they created it or added
 * something through a mini-wizard), and never once they hid them for the course or
 * for good. They are a card in the page, never a pop-up.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt {
    /** @var string The preference that hides every suggestion for a user. */
    const PREF_HIDE = 'tool_wizards_hidesuggestions';

    /** @var int How many next suggestions to show after something is added. */
    const NEXT_SUGGESTIONS = 3;

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
     * Remember what a mini-wizard just added, for the confirmation on the next course page view.
     *
     * @param int $courseid the course
     * @param int $cmid the new course module
     */
    public static function mark_just_added(int $courseid, int $cmid): void {
        global $SESSION;
        $SESSION->tool_wizards_justadded = (object) ['courseid' => $courseid, 'cmid' => $cmid];
    }

    /**
     * Remember that the quiz mini-wizard added the quiz but not its first question.
     */
    public static function mark_question_failed(): void {
        global $SESSION;
        $SESSION->tool_wizards_questionfailed = true;
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
     * Show the suggestions on a course again for the current user, after they hid them there.
     *
     * @param int $courseid the course
     */
    public static function undismiss_course(int $courseid): void {
        global $DB, $USER;
        $DB->delete_records('tool_wizards_dismissed', ['userid' => $USER->id, 'courseid' => $courseid]);
    }

    /**
     * Whether the user has hidden the suggestions on this course.
     *
     * @param int $courseid the course
     * @param int $userid the user
     * @return bool
     */
    public static function is_dismissed(int $courseid, int $userid): bool {
        global $DB;
        return $DB->record_exists('tool_wizards_dismissed', ['userid' => $userid, 'courseid' => $courseid]);
    }

    /**
     * Whether the user has turned all suggestions off.
     *
     * @param int|null $userid the user, default the current one
     * @return bool
     */
    public static function suggestions_hidden(?int $userid = null): bool {
        return (bool) get_user_preferences(self::PREF_HIDE, 0, $userid);
    }

    /**
     * How much content a course has, not counting the Announcements forum every new course
     * gets, or anything being deleted.
     *
     * @param stdClass $course the course
     * @return int number of activities and resources
     */
    public static function count_content(stdClass $course): int {
        global $DB;
        $cms = array_filter(get_fast_modinfo($course)->get_cms(), fn(\cm_info $cm) => !$cm->deletioninprogress);
        $forums = array_filter($cms, fn(\cm_info $cm) => $cm->modname === 'forum');
        if ($forums) {
            // Found with a query: forum_get_course_forum() would create the forum if missing.
            $news = $DB->get_fieldset_select('forum', 'id', "course = ? AND type = 'news'", [$course->id]);
            $cms = array_filter($cms, fn(\cm_info $cm) => !($cm->modname === 'forum' && in_array($cm->instance, $news)));
        }
        return count($cms);
    }

    /**
     * Whether the suggestions may be shown to this user on this course at all
     * (ignoring how much content it has).
     *
     * @param stdClass $course the course
     * @return bool
     */
    public static function may_suggest(stdClass $course): bool {
        global $USER;
        return self::may_add_content($course) && !self::suggestions_hidden() && !self::is_dismissed($course->id, $USER->id);
    }

    /**
     * Whether to offer "Bring back the course wizards" on this course: the user can add content
     * here, and has hidden the suggestions on this course or switched them off everywhere.
     *
     * @param stdClass $course the course
     * @return bool
     */
    public static function may_bring_back(stdClass $course): bool {
        global $USER;
        if (!self::may_add_content($course) || !wizard\repository::for_course($course)) {
            return false;
        }
        // Hidden for this course, or switched off everywhere.
        return self::suggestions_hidden() || self::is_dismissed($course->id, $USER->id);
    }

    /**
     * Bring the suggestions back on a course for the current user: undo hiding them there (and
     * switching them off everywhere), and show the card on the next view of the course page even
     * if the course already has content.
     *
     * @param int $courseid the course
     */
    public static function bring_back(int $courseid): void {
        global $SESSION;
        self::undismiss_course($courseid);
        if (self::suggestions_hidden()) {
            set_user_preference(self::PREF_HIDE, 0);
        }
        $SESSION->tool_wizards_showonce = $courseid;
    }

    /**
     * Whether the user may add content to this course, the condition for any suggestion.
     *
     * @param stdClass $course the course
     * @return bool
     */
    protected static function may_add_content(stdClass $course): bool {
        if (!isloggedin() || isguestuser() || $course->id == SITEID || is_role_switched($course->id)) {
            return false;
        }
        $context = \core\context\course::instance($course->id);
        return !$context->locked && has_capability('moodle/course:manageactivities', $context);
    }

    /**
     * The HTML to add at the top of a course page, if any.
     *
     * @param \moodle_page $page the course page
     * @param \renderer_base $renderer the page renderer
     * @return string
     */
    public static function render_for_page(\moodle_page $page, \renderer_base $renderer): string {
        global $SESSION;
        $course = $page->course;
        if (empty($course->id) || $course->id == SITEID) {
            return '';
        }

        $justcreated = !empty($SESSION->tool_wizards_justcreated) && $SESSION->tool_wizards_justcreated == $course->id;
        unset($SESSION->tool_wizards_justcreated);
        // Just brought back from the help menu: show the card this once, whatever the course holds.
        $showonce = !empty($SESSION->tool_wizards_showonce) && $SESSION->tool_wizards_showonce == $course->id;
        unset($SESSION->tool_wizards_showonce);
        $justadded = null;
        if (!empty($SESSION->tool_wizards_justadded) && $SESSION->tool_wizards_justadded->courseid == $course->id) {
            $justadded = $SESSION->tool_wizards_justadded->cmid;
        }
        unset($SESSION->tool_wizards_justadded);

        $html = messages::render_for_page($course, $renderer);

        if (!self::may_suggest($course)) {
            return $html;
        }
        $types = wizard\repository::for_course($course);
        if (!$types) {
            return $html;
        }
        $threshold = max(0, (int) get_config('tool_wizards', 'emptythreshold'));
        if (!$justcreated && !$justadded && !$showonce && self::count_content($course) > $threshold) {
            return $html;
        }

        $questionfailed = !empty($SESSION->tool_wizards_questionfailed) && $justadded;
        unset($SESSION->tool_wizards_questionfailed);
        $card = new \tool_wizards\output\first_content($course, $types, $justcreated, $justadded, $questionfailed);
        $page->requires->js_call_amd('tool_wizards/first_content', 'init', ['[data-region="tool_wizards-firstcontent"]']);
        return $html . $renderer->render($card);
    }
}
