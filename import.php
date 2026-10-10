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
 * Import wizards from a zip (one or many) or a JSON file holding one wizard or many.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use tool_wizards\local\wizard\packager;

admin_externalpage_setup('tool_wizards_manage');
$url = new moodle_url('/admin/tool/wizards/import.php');
$PAGE->set_url($url);
$manageurl = new moodle_url('/admin/tool/wizards/manage.php');

$form = new tool_wizards\form\import_form($url);
if ($form->is_cancelled()) {
    redirect($manageurl);
}
$results = null;
if ($data = $form->get_data()) {
    $dir = make_request_directory();
    $filename = $form->get_new_filename('file');
    $path = $dir . '/' . clean_param($filename, PARAM_FILE);
    $form->save_file('file', $path, true);
    $results = packager::import($path, $filename, $data->mode);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importwizards', 'tool_wizards'));
if ($results !== null) {
    $table = new html_table();
    $table->head = [get_string('wizard', 'tool_wizards'), get_string('importresult', 'tool_wizards')];
    foreach ($results as $result) {
        $cell = get_string('import_' . $result['result'], 'tool_wizards');
        if ($result['problems']) {
            $cell .= html_writer::alist(array_map('s', $result['problems']), ['class' => 'small text-danger mb-0']);
        }
        $table->data[] = [html_writer::tag('code', s($result['key'])), $cell];
    }
    echo html_writer::table($table);
    echo html_writer::tag('p', get_string('import_drafts', 'tool_wizards'));
    echo $OUTPUT->single_button($manageurl, get_string('managewizards', 'tool_wizards'), 'get');
} else {
    echo html_writer::tag('p', get_string('importwizards_intro', 'tool_wizards'));
    $form->display();
}
echo $OUTPUT->footer();
