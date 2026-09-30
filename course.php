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
 * The course wizard: create a course by answering one question at a time.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use tool_wizards\local\course_creator;
use tool_wizards\local\prefill;

$requested = optional_param('category', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();

$categoryid = course_creator::get_start_category($requested);
if (!$categoryid) {
    // No category where this user may create courses: the same check core's form makes.
    require_capability('moodle/course:create', \core\context\coursecat::instance(
        $requested ?: core_course_category::get_default()->id
    ));
}

$url = new moodle_url('/admin/tool/wizards/course.php', $requested ? ['category' => $requested] : []);
$PAGE->set_url($url);
$PAGE->set_context(\core\context\coursecat::instance($categoryid));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('coursewizard', 'tool_wizards'));
$PAGE->set_heading(get_string('coursewizard', 'tool_wizards'));
$PAGE->navbar->add(get_string('coursewizard', 'tool_wizards'), $url);

// When the wizard is switched off, send people to the standard form instead.
if (!get_config('tool_wizards', 'enabled')) {
    redirect(new moodle_url('/course/edit.php', ['category' => $categoryid]));
}

$errors = [];
$answers = null;
$startstep = '';
if ($action === 'create' || $action === 'full') {
    require_sesskey();
    $answers = course_creator::normalise_answers([
        'fullname' => optional_param('fullname', '', PARAM_TEXT),
        'shortname' => optional_param('shortname', '', PARAM_TEXT),
        'category' => optional_param('categoryid', $categoryid, PARAM_INT),
        'format' => optional_param('format', '', PARAM_PLUGIN),
        'numsections' => optional_param('numsections', '', PARAM_RAW_TRIMMED),
        'startdate' => tool_wizards\local\course_wizard::parse_date(optional_param('startdate', '', PARAM_RAW_TRIMMED)),
        'visible' => optional_param('visible', '', PARAM_RAW_TRIMMED),
    ]);

    if ($action === 'full') {
        // The "Show all settings" button: carry what was typed so far over to the standard form.
        $target = isset(course_creator::get_categories()[$answers->category]) ? $answers->category : $categoryid;
        prefill::stash($answers, $target);
        redirect(new moodle_url('/course/edit.php', ['category' => $target, 'returnto' => 'catmanage']));
    }

    require_capability('moodle/course:create', \core\context\coursecat::instance(
        isset(course_creator::get_categories()[$answers->category]) ? $answers->category : $categoryid
    ));
    $errors = course_creator::validate($answers);
    if (!$errors) {
        $result = course_creator::create($answers);
        $course = $result['course'];
        \tool_wizards\local\prompt::mark_just_created((int) $course->id);
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        if (!$result['enrolled']) {
            redirect(
                $courseurl,
                get_string('notenrolledwarning', 'tool_wizards'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        redirect($courseurl);
    }
    // Show the answers and errors again after a redirect, so the page with the errors never
    // carries the requirements that building core's course form queued, and a refresh does
    // not submit again.
    $SESSION->tool_wizards_retry = (object) [
        'answers' => $answers,
        'errors' => $errors,
        'startstep' => tool_wizards\local\course_wizard::step_for_error(array_key_first($errors)),
    ];
    redirect($url);
} else if (!empty($SESSION->tool_wizards_retry)) {
    $answers = $SESSION->tool_wizards_retry->answers;
    $errors = $SESSION->tool_wizards_retry->errors;
    $startstep = $SESSION->tool_wizards_retry->startstep;
    unset($SESSION->tool_wizards_retry);
}

$wizard = new tool_wizards\output\course_wizard($categoryid, $answers, $errors, $startstep);

echo $OUTPUT->header();
echo $OUTPUT->render($wizard);
echo $OUTPUT->footer();
