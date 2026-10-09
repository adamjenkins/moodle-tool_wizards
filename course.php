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
 * Course wizards: create a course by answering a few questions at a time.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use tool_wizards\local\course_creator;
use tool_wizards\local\prefill;
use tool_wizards\local\wizard\engine;
use tool_wizards\local\wizard\form_builder;
use tool_wizards\local\wizard\repository;
use tool_wizards\local\wizard\text;

$requested = optional_param('category', 0, PARAM_INT);
$key = optional_param('wizard', '', PARAM_ALPHANUMEXT);
$preview = optional_param('preview', 0, PARAM_BOOL);

require_login();

$categoryid = course_creator::get_start_category($requested);
if (!$categoryid) {
    // No category where this user may create courses: the same check core's form makes.
    require_capability('moodle/course:create', \core\context\coursecat::instance(
        $requested ?: core_course_category::get_default()->id
    ));
}
if ($preview) {
    require_capability('tool/wizards:managewizards', \core\context\system::instance());
}

$params = array_filter(['category' => $requested, 'wizard' => $key, 'preview' => (int) $preview]);
$url = new moodle_url('/admin/tool/wizards/course.php', $params);
$PAGE->set_url($url);
$PAGE->set_context(\core\context\coursecat::instance($categoryid));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('coursewizard', 'tool_wizards'));
$PAGE->set_heading(get_string('coursewizard', 'tool_wizards'));
$PAGE->navbar->add(get_string('coursewizard', 'tool_wizards'), $url);

// When the wizards are switched off, send people to the standard form instead.
$wizards = repository::course_wizards();
if (!$preview && (!get_config('tool_wizards', 'enabled') || !$wizards)) {
    redirect(new moodle_url('/course/edit.php', ['category' => $categoryid]));
}

$record = $key !== '' ? repository::get_by_key($key) : (count($wizards) === 1 ? reset($wizards) : null);
if (
    $record && ($record->target !== 'course'
        || (!$preview && (int) $record->status !== repository::STATUS_ENABLED))
) {
    throw new moodle_exception('error_nowizard', 'tool_wizards');
}

if (!$record) {
    // Several course wizards: let the creator choose.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('choosecoursewizard', 'tool_wizards'));
    $html = '';
    foreach ($wizards as $wizard) {
        $doc = repository::definition($wizard);
        $card = form_builder::card_html(
            form_builder::picture($doc['picture'] ?? null, (int) $wizard->id),
            text::get($doc['title']),
            text::get($doc['description'] ?? '')
        );
        $link = new moodle_url('/admin/tool/wizards/course.php', array_filter(['category' => $requested,
            'wizard' => $wizard->wizardkey]));
        $html .= html_writer::link($link, $card, ['class' => 'list-group-item list-group-item-action']);
    }
    echo html_writer::div($html, 'list-group tool_wizards-miniwizard mb-3');
    echo html_writer::link(
        new moodle_url('/course/edit.php', ['category' => $categoryid]),
        get_string('usestandardform', 'tool_wizards')
    );
    echo $OUTPUT->footer();
    die();
}

$engine = new engine(repository::definition($record), (int) $record->id, null, $categoryid);
$PAGE->set_title(text::get($engine->definition()['title']));
$form = new tool_wizards\form\course_wizard_form($url, ['engine' => $engine, 'preview' => $preview]);
$builder = new form_builder($engine, $form->get_form_for_wizard());
$form->set_data(['wizard' => $record->wizardkey, 'startcategory' => $categoryid, 'preview' => (int) $preview,
    'category' => $categoryid] + $builder->draft_defaults());

if (!$preview && optional_param('fullsettings', 0, PARAM_BOOL) && confirm_sesskey()) {
    // The "Show all settings" button: carry what was typed so far over to the standard form.
    $answers = $engine->answers((array) $form->get_submitted_data());
    $fields = course_creator::prepare_fields($engine->fields($answers), $categoryid);
    $target = isset(course_creator::get_categories()[$fields->category]) ? $fields->category : $categoryid;
    prefill::stash($fields, $target);
    redirect(new moodle_url('/course/edit.php', ['category' => $target, 'returnto' => 'catmanage']));
}

$lines = null;
if ($data = $form->get_data()) {
    $answers = $engine->answers((array) $data);
    if ($preview) {
        $lines = $engine->describe($answers);
    } else {
        $fields = course_creator::prepare_fields($engine->fields($answers), $categoryid);
        require_capability('moodle/course:create', \core\context\coursecat::instance($fields->category));
        $result = course_creator::create($fields);
        $course = $result['course'];
        foreach ($engine->actions($answers) as [$class, $config]) {
            $class::run($config, $answers, $course);
        }
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
}

echo $OUTPUT->header();
echo $OUTPUT->heading(text::get($engine->definition()['title']));
if ($lines !== null) {
    echo $OUTPUT->notification(get_string('preview_result', 'tool_wizards'), 'info', false);
    echo html_writer::alist(array_map('s', $lines ?: [get_string('preview_nothing', 'tool_wizards')]));
}
echo html_writer::start_div('tool_wizards-coursewizard');
$form->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
