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
 * Edit a wizard: texts in every language, screen order, pictures; or improve it with AI.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use tool_wizards\local\wizard\ai_assist;
use tool_wizards\local\wizard\defaults;
use tool_wizards\local\wizard\invalid_definition;
use tool_wizards\local\wizard\repository;
use tool_wizards\local\wizard\text;

$id = required_param('id', PARAM_INT);
$aiaction = optional_param('aiaction', '', PARAM_ALPHA);
admin_externalpage_setup('tool_wizards_manage');
$url = new moodle_url('/admin/tool/wizards/edit.php', ['id' => $id]);
$PAGE->set_url($url);
$manageurl = new moodle_url('/admin/tool/wizards/manage.php');

$record = repository::get($id);
if (!$record) {
    throw new moodle_exception('error_nowizard', 'tool_wizards');
}
$doc = repository::definition($record);
$context = \core\context\system::instance();
$problems = [];

// A proposal from the AI waiting to be applied or discarded.
$proposal = $SESSION->tool_wizards_ai[$id] ?? null;
if ($aiaction !== '' && $proposal && confirm_sesskey()) {
    unset($SESSION->tool_wizards_ai[$id]);
    if ($aiaction === 'apply') {
        try {
            repository::save($proposal['doc'], [], $id);
            redirect($url, get_string('ai_applied', 'tool_wizards'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (invalid_definition $e) {
            $problems = $e->problems;
        }
    } else {
        redirect($url);
    }
}

$aiavailable = ai_assist::available();
$aiform = null;
if ($aiavailable) {
    $aiform = new tool_wizards\form\ai_form($url, ['id' => $id,
        'policyaccepted' => ai_assist::policy_accepted((int) $USER->id)]);
    if ($aidata = $aiform->get_data()) {
        if (!empty($aidata->acceptpolicy)) {
            ai_assist::accept_policy((int) $USER->id);
        }
        // A language model can take a while, especially a local one.
        core_php_time_limit::raise(300);
        $result = ai_assist::improve($doc, $aidata->instruction, (int) $USER->id);
        if ($result['error'] !== null) {
            redirect(
                $url,
                get_string('ai_failed', 'tool_wizards', s($result['error'])),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $SESSION->tool_wizards_ai[$id] = ['doc' => $result['doc'], 'problems' => $result['problems'],
            'instruction' => $aidata->instruction];
        redirect($url);
    }
}

$form = new tool_wizards\form\edit_form($url, ['record' => $record, 'doc' => $doc]);
if ($form->is_cancelled()) {
    redirect($manageurl);
}
if ($data = $form->get_data()) {
    file_save_draft_area_files($data->pictures, $context->id, 'tool_wizards', 'picture', $id, ['subdirs' => 0]);
    $newdoc = tool_wizards\form\edit_form::apply($doc, $data);
    try {
        repository::save($newdoc, [], $id);
        repository::set_status($id, (int) $data->status);
        redirect($manageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (invalid_definition $e) {
        $problems = $e->problems;
    }
}
$draftitemid = file_get_submitted_draft_itemid('pictures');
file_prepare_draft_area($draftitemid, $context->id, 'tool_wizards', 'picture', $id, ['subdirs' => 0]);
$form->set_data(tool_wizards\form\edit_form::values($record, $doc) + ['pictures' => $draftitemid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editwizard', 'tool_wizards', s(text::get($doc['title'] ?? $record->wizardkey))));
if ($problems) {
    echo $OUTPUT->notification(get_string('error_invaliddefinition', 'tool_wizards')
        . html_writer::alist(array_map('s', $problems)), 'error', false);
}

$links = [
    html_writer::link(
        new moodle_url('/admin/tool/wizards/try.php', ['id' => $id]),
        get_string('tryit', 'tool_wizards'),
        ['class' => 'btn btn-secondary']
    ),
    html_writer::link(
        new moodle_url('/admin/tool/wizards/editjson.php', ['id' => $id]),
        get_string('editjson', 'tool_wizards'),
        ['class' => 'btn btn-secondary']
    ),
];
echo html_writer::div(implode(' ', $links), 'd-flex flex-wrap gap-2 mb-3');

// Improve with AI.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo $OUTPUT->heading(get_string('ai_heading', 'tool_wizards'), 3, 'h5');
if (!$aiavailable) {
    echo html_writer::tag('p', get_string('ai_unavailable', 'tool_wizards'), ['class' => 'mb-0 text-body-secondary']);
} else if ($proposal) {
    echo html_writer::tag('p', get_string('ai_proposal', 'tool_wizards', s($proposal['instruction'])));
    $lines = $proposal['doc'] ? defaults::compare($doc, $proposal['doc']) : [];
    echo $lines ? html_writer::alist(array_map('s', $lines), ['class' => 'tool_wizards-compare small'])
        : html_writer::tag('p', get_string('ai_nochanges', 'tool_wizards'));
    if ($proposal['problems']) {
        echo $OUTPUT->notification(get_string('ai_problems', 'tool_wizards')
            . html_writer::alist(array_map('s', $proposal['problems'])), 'warning', false);
    }
    echo html_writer::start_div('d-flex gap-2');
    if (!$proposal['problems'] && $lines) {
        echo $OUTPUT->single_button(
            new moodle_url($url, ['aiaction' => 'apply', 'sesskey' => sesskey()]),
            get_string('ai_apply', 'tool_wizards'),
            'post',
            ['type' => single_button::BUTTON_PRIMARY]
        );
    }
    echo $OUTPUT->single_button(
        new moodle_url($url, ['aiaction' => 'discard', 'sesskey' => sesskey()]),
        get_string('ai_discard', 'tool_wizards'),
        'post'
    );
    echo html_writer::end_div();
} else {
    echo html_writer::tag('p', get_string('ai_intro', 'tool_wizards'));
    $aiform->display();
}
echo html_writer::end_div();
echo html_writer::end_div();

$form->display();
echo $OUTPUT->footer();
