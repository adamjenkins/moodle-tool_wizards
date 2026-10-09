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
 * The wizard for building wizards: choose what it creates, pick that form's settings,
 * word the questions and group them into screens, optionally add purposes, and save a draft.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use tool_wizards\form\builder\purposes_form;
use tool_wizards\form\builder\questions_form;
use tool_wizards\form\builder\settings_form;
use tool_wizards\form\builder\target_form;
use tool_wizards\local\wizard\builder;
use tool_wizards\local\wizard\introspector;
use tool_wizards\local\wizard\invalid_definition;
use tool_wizards\local\wizard\repository;

$step = optional_param('step', 1, PARAM_INT);
admin_externalpage_setup('tool_wizards_manage');
$manageurl = new moodle_url('/admin/tool/wizards/manage.php');
$stepurl = fn($n) => new moodle_url('/admin/tool/wizards/build.php', ['step' => $n]);
$PAGE->set_url($stepurl($step));

$state = $SESSION->tool_wizards_builder ?? null;
if ($step > 1 && !$state) {
    redirect($stepurl(1));
}

$steps = [1 => 'build_step_target', 2 => 'build_step_settings', 3 => 'build_step_questions', 4 => 'build_step_purposes'];
$problems = [];
switch ($step) {
    case 1:
        $form = new target_form($stepurl(1));
        if ($form->is_cancelled()) {
            unset($SESSION->tool_wizards_builder);
            redirect($manageurl);
        }
        if ($data = $form->get_data()) {
            $SESSION->tool_wizards_builder = ['target' => $data->target, 'title' => $data->title,
                'description' => $data->description, 'shared' => !empty($data->shared)];
            redirect($stepurl(2));
        }
        if ($state) {
            $form->set_data($state);
        }
        break;
    case 2:
        $settings = introspector::settings($state['target']);
        // What the form itself requires is always asked.
        $required = array_merge(
            $state['target'] === 'course' ? ['fullname'] : ['name'],
            array_column(array_filter($settings, fn($s) => !empty($s['required'])), 'field')
        );
        $form = new settings_form($stepurl(2), ['settings' => $settings, 'required' => $required]);
        if ($form->is_cancelled()) {
            redirect($stepurl(1));
        }
        if ($data = $form->get_data()) {
            $picked = array_keys(array_filter((array) ($data->pick ?? [])));
            $picked = array_unique(array_merge($required, $picked));
            $state['settings'] = array_values(array_filter($settings, fn($s) => in_array($s['field'], $picked, true)));
            $SESSION->tool_wizards_builder = $state;
            redirect($stepurl(3));
        }
        $defaults = [];
        foreach ($state['settings'] ?? [] as $setting) {
            $defaults['pick[' . $setting['field'] . ']'] = 1;
        }
        $form->set_data($defaults);
        break;
    case 3:
        $form = new questions_form($stepurl(3), ['settings' => $state['settings']]);
        if ($form->is_cancelled()) {
            redirect($stepurl(2));
        }
        if ($data = $form->get_data()) {
            $state['answers'] = json_decode(json_encode($data->q ?? []), true);
            $state['questions'] = builder::questions($state['target'], $state['settings'], $state['answers']);
            $SESSION->tool_wizards_builder = $state;
            redirect($stepurl(4));
        }
        $form->set_data(questions_form::defaults($state['settings']));
        break;
    case 4:
        $questions = array_column($state['questions'], 'question');
        $form = new purposes_form($stepurl(4), ['questions' => $questions]);
        if ($form->is_cancelled()) {
            redirect($stepurl(3));
        }
        if ($data = $form->get_data()) {
            $state['purposequestion'] = $data->purposequestion;
            $state['purposes'] = json_decode(json_encode($data->p ?? []), true);
            $doc = builder::definition($state);
            try {
                $id = repository::save($doc, ['status' => repository::STATUS_DRAFT, 'origin' => repository::ORIGIN_CUSTOM]);
                unset($SESSION->tool_wizards_builder);
                redirect(
                    new moodle_url('/admin/tool/wizards/edit.php', ['id' => $id]),
                    get_string('build_saved', 'tool_wizards'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } catch (invalid_definition $e) {
                $problems = $e->problems;
            }
        }
        break;
    default:
        redirect($stepurl(1));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('buildwizard', 'tool_wizards'));
$items = '';
foreach ($steps as $n => $key) {
    $class = $n === $step ? 'active' : ($n < $step ? 'done' : '');
    $items .= html_writer::tag('li', get_string($key, 'tool_wizards'), ['class' => $class,
        'aria-current' => $n === $step ? 'step' : null]);
}
echo html_writer::tag('ol', $items, ['class' => 'tool_wizards-progress list-unstyled d-flex flex-wrap gap-1 small mb-3',
    'aria-label' => get_string('progress', 'tool_wizards')]);
if ($problems) {
    echo $OUTPUT->notification(get_string('error_invaliddefinition', 'tool_wizards')
        . html_writer::alist(array_map('s', $problems)), 'error', false);
}
if ($step === 2 && !$settings) {
    echo $OUTPUT->notification(get_string('build_nosettings', 'tool_wizards'), 'warning');
}
$form->display();
echo $OUTPUT->footer();
