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

namespace tool_wizards\form;

use core_form\dynamic_form;
use tool_wizards\local\module_creator;
use tool_wizards\local\prompt;
use tool_wizards\local\wizard\engine;
use tool_wizards\local\wizard\form_builder;
use tool_wizards\local\wizard\repository;

/**
 * Any activity wizard, shown in a modal a screen at a time.
 *
 * Arguments: wizard (key), courseid, section (optional), preview (1 for "Try it": nothing
 * is created; the answer is what would be set). Everything from the browser is checked again.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard_form extends dynamic_form {
    /** @var \stdClass|null the course */
    protected ?\stdClass $course = null;

    /** @var \stdClass|null the wizard record */
    protected ?\stdClass $record = null;

    /** @var engine|null the engine */
    protected ?engine $engine = null;

    /**
     * The course, from the submitted id.
     *
     * @return \stdClass
     */
    protected function get_course(): \stdClass {
        if ($this->course === null) {
            $this->course = get_course($this->optional_param('courseid', 0, PARAM_INT));
        }
        return $this->course;
    }

    /**
     * The wizard, from the submitted key.
     *
     * @return \stdClass
     */
    protected function get_record(): \stdClass {
        if ($this->record === null) {
            $record = repository::get_by_key($this->optional_param('wizard', '', PARAM_ALPHANUMEXT));
            if (!$record || $record->target === 'course' || repository::is_content($record)) {
                throw new \moodle_exception('error_nowizard', 'tool_wizards');
            }
            $this->record = $record;
        }
        return $this->record;
    }

    /**
     * The engine.
     *
     * @return engine
     */
    protected function engine(): engine {
        if ($this->engine === null) {
            $record = $this->get_record();
            $this->engine = new engine(repository::definition($record), (int) $record->id, $this->get_course());
        }
        return $this->engine;
    }

    /**
     * Whether this is "Try it".
     *
     * @return bool
     */
    protected function is_preview(): bool {
        return (bool) $this->optional_param('preview', 0, PARAM_BOOL);
    }

    /**
     * The section, or null for the default one.
     *
     * @return int|null
     */
    protected function section(): ?int {
        $section = $this->optional_param('section', -1, PARAM_INT);
        return $section >= 0 ? $section : null;
    }

    /**
     * The course context.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \core\context\course::instance($this->get_course()->id);
    }

    /**
     * Only people who may add this kind of activity here, with a wizard they may run.
     */
    protected function check_access_for_dynamic_submission(): void {
        $course = $this->get_course();
        require_login($course, false, null, false, true);
        require_capability('moodle/course:manageactivities', $this->get_context_for_dynamic_submission());
        $record = $this->get_record();
        if ($this->is_preview()) {
            require_capability('tool/wizards:managewizards', \core\context\system::instance());
        } else if (!get_config('tool_wizards', 'enabled') || (int) $record->status !== repository::STATUS_ENABLED) {
            throw new \moodle_exception('error_nowizard', 'tool_wizards');
        }
        if (!module_creator::is_available($course, $record->target)) {
            throw new \moodle_exception('error_notavailable', 'tool_wizards');
        }
    }

    /**
     * The screens.
     */
    protected function definition() {
        $mform = $this->_form;
        $hidden = ['courseid' => PARAM_INT, 'wizard' => PARAM_ALPHANUMEXT, 'section' => PARAM_INT, 'preview' => PARAM_INT];
        foreach ($hidden as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }
        $label = $this->is_preview() ? get_string('preview_finish', 'tool_wizards') : get_string('add', 'core');
        (new form_builder($this->engine(), $mform))->build($label, $this->is_preview());
    }

    /**
     * Mark the form for the stepper.
     */
    public function definition_after_data() {
        $this->_form->updateAttributes(['class' => trim(($this->_form->getAttribute('class') ?? '')
            . ' tool_wizards-miniwizard tool_wizards-wizard-' . $this->get_record()->wizardkey)]);
    }

    /**
     * Render with the stepper. It is asked for here, not in the definition: the modal collects a
     * form's JavaScript only while rendering it (core_form\external\dynamic_form::execute()).
     *
     * @return string
     */
    public function render() {
        global $PAGE;
        $PAGE->requires->js_call_amd(
            'tool_wizards/modal_stepper',
            'init',
            ['.tool_wizards-wizard-' . $this->get_record()->wizardkey]
        );
        return parent::render();
    }

    /**
     * Initial values.
     */
    public function set_data_for_dynamic_submission(): void {
        $builder = new form_builder($this->engine(), $this->_form);
        $this->set_data([
            'courseid' => $this->get_course()->id,
            'wizard' => $this->get_record()->wizardkey,
            'section' => $this->section() ?? -1,
            'preview' => (int) $this->is_preview(),
        ] + $builder->draft_defaults());
    }

    /**
     * The course page.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->get_course()->id]);
    }

    /**
     * The wizard's own checks, then the activity form's, each on the question it concerns.
     *
     * @param array $data submitted data
     * @param array $files uploaded files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $engine = $this->engine();
        $answers = $engine->answers($data);
        foreach ($engine->check($answers) as $key => $message) {
            $errors[$engine->element_for($key)] = $message;
        }
        if ($errors) {
            return $errors;
        }
        $moduleerrors = module_creator::check(
            $this->get_course(),
            $engine->modname(),
            $engine->fields($answers),
            $this->section()
        );
        return $engine->place_errors($moduleerrors);
    }

    /**
     * Create the activity (or, for "Try it", say what would be set).
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $engine = $this->engine();
        $answers = $engine->answers((array) $this->get_data());
        if ($this->is_preview()) {
            return ['preview' => true, 'lines' => $engine->describe($answers)];
        }
        $course = $this->get_course();
        $cm = module_creator::create($course, $engine->modname(), $engine->fields($answers), $this->section());
        foreach ($engine->actions($answers) as [$class, $config]) {
            $class::run($config, $answers, $cm);
        }
        prompt::mark_just_added($course->id, $cm->id);
        return ['preview' => false, 'cmid' => $cm->id, 'name' => $cm->get_formatted_name()];
    }
}
