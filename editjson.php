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
 * Edit a wizard's definition as JSON (advanced). Saved only when it passes the validator.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use tool_wizards\local\wizard\invalid_definition;
use tool_wizards\local\wizard\repository;
use tool_wizards\local\wizard\text;
use tool_wizards\local\wizard\validator;

$id = required_param('id', PARAM_INT);
admin_externalpage_setup('tool_wizards_manage');
$url = new moodle_url('/admin/tool/wizards/editjson.php', ['id' => $id]);
$PAGE->set_url($url);
$record = repository::get($id);
if (!$record) {
    throw new moodle_exception('error_nowizard', 'tool_wizards');
}
$form = new tool_wizards\form\json_form($url);
$editurl = new moodle_url('/admin/tool/wizards/edit.php', ['id' => $id]);
if ($form->is_cancelled()) {
    redirect($editurl);
}
$problems = [];
if ($data = $form->get_data()) {
    $doc = json_decode($data->json, true);
    $problems = is_array($doc) ? validator::check($doc) : ['(document): not valid JSON: ' . json_last_error_msg()];
    if (!$problems) {
        try {
            repository::save($doc, [], $id);
            redirect($editurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (invalid_definition $e) {
            $problems = $e->problems;
        }
    }
} else {
    $form->set_data(['id' => $id, 'json' => $record->definition]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editjson', 'tool_wizards') . ': '
    . s(text::get(repository::definition($record)['title'] ?? $record->wizardkey)));
echo html_writer::tag('p', get_string('editjson_intro', 'tool_wizards', (new moodle_url('/admin/tool/wizards/runbook.php'))
    ->out()));
if ($problems) {
    echo $OUTPUT->notification(get_string('error_invaliddefinition', 'tool_wizards')
        . html_writer::alist(array_map('s', $problems)), 'error', false);
}
$form->display();
echo $OUTPUT->footer();
