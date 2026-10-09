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
 * "Try it": run a wizard without creating anything. Activity wizards open on a course page
 * of the admin's choice; course wizards on the course wizard page.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use tool_wizards\local\wizard\repository;
use tool_wizards\local\wizard\text;

$id = required_param('id', PARAM_INT);
admin_externalpage_setup('tool_wizards_manage');
$record = repository::get($id);
if (!$record) {
    throw new moodle_exception('error_nowizard', 'tool_wizards');
}
if ($record->target === 'course') {
    redirect(new moodle_url('/admin/tool/wizards/course.php', ['wizard' => $record->wizardkey, 'preview' => 1]));
}

$url = new moodle_url('/admin/tool/wizards/try.php', ['id' => $id]);
$PAGE->set_url($url);
$form = new tool_wizards\form\try_form($url, ['modname' => $record->target]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/wizards/manage.php'));
}
if ($data = $form->get_data()) {
    set_user_preference('tool_wizards_trycourse', (int) $data->courseid);
    redirect(new moodle_url('/course/view.php', ['id' => $data->courseid, 'tool_wizards_try' => $record->wizardkey]));
}
$form->set_data(['id' => $id, 'courseid' => (int) get_user_preferences('tool_wizards_trycourse', 0)]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tryit_heading', 'tool_wizards', s(text::get(repository::definition($record)['title']))));
echo html_writer::tag('p', get_string('tryit_intro', 'tool_wizards'));
$form->display();
echo $OUTPUT->footer();
