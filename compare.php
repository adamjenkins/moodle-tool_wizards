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
 * Compare an edited default wizard with the shipped version, then reset it or keep the edits.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_wizards\local\wizard\defaults;
use tool_wizards\local\wizard\repository;
use tool_wizards\local\wizard\text;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
admin_externalpage_setup('tool_wizards_manage');
$url = new moodle_url('/admin/tool/wizards/compare.php', ['id' => $id]);
$PAGE->set_url($url);
$manageurl = new moodle_url('/admin/tool/wizards/manage.php');

$record = repository::get($id);
$shipped = $record ? (defaults::shipped()[$record->wizardkey] ?? null) : null;
if (!$record || $record->origin !== repository::ORIGIN_DEFAULT || !$shipped) {
    throw new moodle_exception('error_nowizard', 'tool_wizards');
}
if ($action === 'reset' && confirm_sesskey()) {
    defaults::reset($id);
    redirect($manageurl, get_string('resetdone', 'tool_wizards'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'keep' && confirm_sesskey()) {
    defaults::keep_mine($id);
    redirect($manageurl);
}

$mine = repository::definition($record);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('compare_heading', 'tool_wizards', s(text::get($mine['title'] ?? $record->wizardkey))));
$lines = defaults::compare($mine, $shipped);
echo html_writer::tag('p', get_string('compare_intro', 'tool_wizards'));
echo $lines ? html_writer::alist(array_map('s', $lines), ['class' => 'tool_wizards-compare'])
    : $OUTPUT->notification(get_string('compare_same', 'tool_wizards'), 'info');
echo html_writer::start_div('d-flex gap-2');
echo $OUTPUT->single_button(
    new moodle_url($url, ['action' => 'reset', 'sesskey' => sesskey()]),
    get_string('resettodefault', 'tool_wizards'),
    'post',
    ['type' => single_button::BUTTON_DANGER]
);
if ((int) $record->newerversion > 0) {
    echo $OUTPUT->single_button(
        new moodle_url($url, ['action' => 'keep', 'sesskey' => sesskey()]),
        get_string('keepmine', 'tool_wizards'),
        'post'
    );
}
echo $OUTPUT->single_button($manageurl, get_string('back'), 'get');
echo html_writer::end_div();
echo $OUTPUT->footer();
