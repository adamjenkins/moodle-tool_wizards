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
 * Manage wizards: the list, with per-wizard and bulk actions.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_wizards\local\wizard\defaults;
use tool_wizards\local\wizard\form_builder;
use tool_wizards\local\wizard\packager;
use tool_wizards\local\wizard\repository;
use tool_wizards\local\wizard\text;
use tool_wizards\local\wizard\texts;

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('tool_wizards_manage');
$url = new moodle_url('/admin/tool/wizards/manage.php');

// Single-wizard actions.
if ($action !== '' && $id) {
    require_sesskey();
    $record = repository::get($id);
    if (!$record) {
        throw new moodle_exception('error_nowizard', 'tool_wizards');
    }
    switch ($action) {
        case 'enable':
            repository::set_status($id, repository::STATUS_ENABLED);
            break;
        case 'disable':
            repository::set_status($id, repository::STATUS_DISABLED);
            break;
        case 'up':
        case 'down':
            repository::move($id, $action === 'up' ? -1 : 1);
            break;
        case 'duplicate':
            $newid = repository::duplicate($id);
            redirect(
                new moodle_url('/admin/tool/wizards/edit.php', ['id' => $newid]),
                get_string('duplicated', 'tool_wizards'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        case 'export':
            $path = packager::export([$id]);
            send_file($path, 'wizard-' . $record->wizardkey . '.zip', 0, 0, false, true, 'application/zip');
            die();
        case 'delete':
            if (!optional_param('confirm', 0, PARAM_BOOL)) {
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(
                    get_string(
                        'deleteconfirm',
                        'tool_wizards',
                        s(text::get(repository::definition($record)['title'] ?? $record->wizardkey))
                    ),
                    new moodle_url($url, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
                    $url
                );
                echo $OUTPUT->footer();
                die();
            }
            repository::delete($id);
            break;
    }
    redirect($url);
}

// Bulk actions.
$bulk = optional_param('bulkaction', '', PARAM_ALPHA);
$ids = optional_param_array('ids', [], PARAM_INT);
if ($bulk !== '' && data_submitted() && confirm_sesskey()) {
    $ids = array_values(array_filter($ids, fn($wizardid) => repository::get((int) $wizardid)));
    if (!$ids) {
        redirect($url, get_string('nothingselected', 'tool_wizards'), null, \core\output\notification::NOTIFY_WARNING);
    }
    switch ($bulk) {
        case 'enable':
        case 'disable':
            foreach ($ids as $wizardid) {
                repository::set_status($wizardid, $bulk === 'enable' ? repository::STATUS_ENABLED : repository::STATUS_DISABLED);
            }
            break;
        case 'export':
            $path = packager::export($ids);
            send_file($path, 'wizards-' . date('Y-m-d') . '.zip', 0, 0, false, true, 'application/zip');
            die();
        case 'delete':
            if (!optional_param('confirm', 0, PARAM_BOOL)) {
                echo $OUTPUT->header();
                $params = ['bulkaction' => 'delete', 'confirm' => 1, 'sesskey' => sesskey()];
                foreach ($ids as $i => $wizardid) {
                    $params["ids[$i]"] = $wizardid;
                }
                echo $OUTPUT->confirm(
                    get_string('bulkdeleteconfirm', 'tool_wizards', count($ids)),
                    new single_button(new moodle_url($url, $params), get_string('delete'), 'post'),
                    $url
                );
                echo $OUTPUT->footer();
                die();
            }
            $skipped = 0;
            foreach ($ids as $wizardid) {
                if (!repository::delete($wizardid)) {
                    $skipped++;
                }
            }
            if ($skipped) {
                redirect(
                    $url,
                    get_string('defaultsnotdeleted', 'tool_wizards', $skipped),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
            }
            break;
    }
    redirect($url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managewizards', 'tool_wizards'));
echo html_writer::tag('p', get_string('managewizards_intro', 'tool_wizards'));

$buttons = [
    html_writer::link(
        new moodle_url('/admin/tool/wizards/build.php'),
        get_string('buildwizard', 'tool_wizards'),
        ['class' => 'btn btn-primary']
    ),
    html_writer::link(
        new moodle_url('/admin/tool/wizards/import.php'),
        get_string('importwizards', 'tool_wizards'),
        ['class' => 'btn btn-secondary']
    ),
    html_writer::link(
        new moodle_url('/admin/tool/wizards/runbook.php'),
        get_string('downloadrunbook', 'tool_wizards'),
        ['class' => 'btn btn-secondary']
    ),
];
echo html_writer::div(implode(' ', $buttons), 'd-flex flex-wrap gap-2 mb-3');
if (!get_config('tool_wizards', 'enabled')) {
    echo $OUTPUT->notification(get_string('wizardsoff', 'tool_wizards'), 'warning');
}

$statuses = [
    repository::STATUS_ENABLED => ['status_enabled', 'bg-success'],
    repository::STATUS_DISABLED => ['status_disabled', 'bg-secondary'],
    repository::STATUS_DRAFT => ['status_draft', 'bg-warning text-dark'],
];
$table = new html_table();
$table->attributes['class'] = 'table generaltable tool_wizards-manage';
$table->head = [
    html_writer::checkbox(
        'selectall',
        1,
        false,
        html_writer::span(get_string('selectall'), 'visually-hidden'),
        ['data-action' => 'tool_wizards-selectall']
    ),
    get_string('order', 'tool_wizards'), get_string('wizard', 'tool_wizards'), get_string('status', 'tool_wizards'),
    get_string('origin', 'tool_wizards'), get_string('languages', 'tool_wizards'), get_string('actions'),
];
$all = array_values(repository::all());
foreach ($all as $index => $record) {
    $doc = repository::definition($record);
    $title = text::get($doc['title'] ?? $record->wizardkey);
    $target = $record->target === 'course' ? get_string('target_course', 'tool_wizards')
        : (get_string_manager()->string_exists('modulename', 'mod_' . $record->target)
            ? get_string('modulename', 'mod_' . $record->target) : $record->target);
    $picture = form_builder::picture(
        $doc['picture'] ?? ($doc['screens'][0]['items'][0]['choices'][0]['picture'] ?? null),
        (int) $record->id
    );
    $wizardcell = html_writer::div(
        ($picture !== '' ? html_writer::span($picture, 'tool_wizards-listpicture', ['aria-hidden' => 'true']) : '')
        . html_writer::div(html_writer::tag('strong', s($title)) . html_writer::div(s($target) . ' · '
            . html_writer::tag('code', s($record->wizardkey)), 'small text-body-secondary')),
        'd-flex gap-2 align-items-center'
    );

    [$statusstring, $statusclass] = $statuses[(int) $record->status] ?? $statuses[repository::STATUS_DISABLED];
    $status = html_writer::span(get_string($statusstring, 'tool_wizards'), 'badge ' . $statusclass);

    $origin = get_string('origin_' . $record->origin, 'tool_wizards');
    if ($record->origin === repository::ORIGIN_DEFAULT) {
        if (!empty($record->retired)) {
            $origin = get_string('origin_retired', 'tool_wizards');
        } else if ((int) $record->newerversion > 0) {
            $origin = html_writer::link(
                new moodle_url('/admin/tool/wizards/compare.php', ['id' => $record->id]),
                get_string('origin_newer', 'tool_wizards'),
                ['class' => 'badge bg-info text-dark']
            );
        } else if (repository::is_edited($record)) {
            $origin = get_string('origin_edited', 'tool_wizards');
        }
    }

    $actionurl = fn($name) => new moodle_url($url, ['action' => $name, 'id' => $record->id, 'sesskey' => sesskey()]);
    $links = [
        html_writer::link(new moodle_url('/admin/tool/wizards/edit.php', ['id' => $record->id]), get_string('edit')),
        html_writer::link(
            new moodle_url('/admin/tool/wizards/try.php', ['id' => $record->id]),
            get_string('tryit', 'tool_wizards')
        ),
        html_writer::link($actionurl('duplicate'), get_string('duplicate', 'tool_wizards')),
        html_writer::link($actionurl('export'), get_string('export', 'tool_wizards')),
        (int) $record->status === repository::STATUS_ENABLED
            ? html_writer::link($actionurl('disable'), get_string('disable'))
            : html_writer::link($actionurl('enable'), get_string('enable')),
    ];
    if (
        $record->origin === repository::ORIGIN_DEFAULT && empty($record->retired)
            && (repository::is_edited($record) || (int) $record->newerversion > 0)
    ) {
        $links[] = html_writer::link(
            new moodle_url('/admin/tool/wizards/compare.php', ['id' => $record->id]),
            get_string('compare', 'tool_wizards')
        );
    }
    if ($record->origin !== repository::ORIGIN_DEFAULT || !empty($record->retired)) {
        $links[] = html_writer::link($actionurl('delete'), get_string('delete'), ['class' => 'text-danger']);
    }

    $order = ($index > 0 ? html_writer::link($actionurl('up'), $OUTPUT->pix_icon('t/up', get_string('moveup'))) : '')
        . ($index < count($all) - 1 ? html_writer::link($actionurl('down'), $OUTPUT->pix_icon('t/down', get_string('movedown')))
            : '');

    $table->data[] = [
        html_writer::checkbox('ids[]', $record->id, false, html_writer::span(
            get_string('selectwizard', 'tool_wizards', s($title)),
            'visually-hidden'
        ), ['class' => 'tool_wizards-select']),
        $order,
        $wizardcell,
        $status,
        $origin,
        s(implode(', ', texts::complete_languages($doc))),
        implode(' · ', $links),
    ];
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::table($table);
$bulkoptions = [
    'enable' => get_string('bulk_enable', 'tool_wizards'),
    'disable' => get_string('bulk_disable', 'tool_wizards'),
    'export' => get_string('bulk_export', 'tool_wizards'),
    'delete' => get_string('bulk_delete', 'tool_wizards'),
];
echo html_writer::div(
    html_writer::label(get_string('withselected', 'tool_wizards'), 'tool_wizards-bulkaction', true, ['class' => 'me-2'])
    . html_writer::select($bulkoptions, 'bulkaction', '', ['' => 'choosedots'], ['id' => 'tool_wizards-bulkaction',
        'class' => 'form-select w-auto d-inline-block me-2'])
    . html_writer::tag('button', get_string('go'), ['type' => 'submit', 'class' => 'btn btn-secondary']),
    'd-flex align-items-center mb-4'
);
echo html_writer::end_tag('form');
$PAGE->requires->js_amd_inline("
    document.querySelector('[data-action=\"tool_wizards-selectall\"]')?.addEventListener('change', e => {
        document.querySelectorAll('.tool_wizards-select').forEach(box => { box.checked = e.target.checked; });
    });");
echo $OUTPUT->footer();
