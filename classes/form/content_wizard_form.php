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
use tool_wizards\local\content_creator;
use tool_wizards\local\wizard\engine;
use tool_wizards\local\wizard\form_builder;
use tool_wizards\local\wizard\repository;

/**
 * Any in-activity wizard (a question, a lesson page, ...), shown in a modal a screen at a time.
 *
 * Arguments: wizard (key), cmid (the activity), preview (1 for "Try it": nothing is saved; the
 * answer is what would be set). Everything from the browser is checked again.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_wizard_form extends dynamic_form {
    /** @var \cm_info|null the activity */
    protected ?\cm_info $cm = null;

    /** @var \stdClass|null the wizard record */
    protected ?\stdClass $record = null;

    /** @var engine|null the engine */
    protected ?engine $engine = null;

    /**
     * The activity, from the submitted id.
     *
     * @return \cm_info
     */
    protected function get_cm(): \cm_info {
        if ($this->cm === null) {
            [, $cm] = get_course_and_cm_from_cmid($this->optional_param('cmid', 0, PARAM_INT));
            $this->cm = $cm;
        }
        return $this->cm;
    }

    /**
     * The wizard, from the submitted key.
     *
     * @return \stdClass
     */
    protected function get_record(): \stdClass {
        if ($this->record === null) {
            $record = repository::get_by_key($this->optional_param('wizard', '', PARAM_ALPHANUMEXT));
            if (!$record || !repository::is_content($record)) {
                throw new \moodle_exception('error_nowizard', 'tool_wizards');
            }
            $this->record = $record;
        }
        return $this->record;
    }

    /**
     * The definition.
     *
     * @return array
     */
    protected function doc(): array {
        return repository::definition($this->get_record());
    }

    /**
     * The engine.
     *
     * @return engine
     */
    protected function engine(): engine {
        if ($this->engine === null) {
            $record = $this->get_record();
            $this->engine = (new engine($this->doc(), (int) $record->id))->set_cm($this->get_cm());
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
     * The activity's context.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return $this->get_cm()->context;
    }

    /**
     * Only people who may change this activity, with a wizard they may run.
     */
    protected function check_access_for_dynamic_submission(): void {
        $cm = $this->get_cm();
        require_login($cm->course, false, $cm, false, true);
        $record = $this->get_record();
        if ($this->is_preview()) {
            require_capability('tool/wizards:managewizards', \core\context\system::instance());
        } else if (!get_config('tool_wizards', 'enabled') || (int) $record->status !== repository::STATUS_ENABLED) {
            throw new \moodle_exception('error_nowizard', 'tool_wizards');
        }
        if (!content_creator::may_use($this->doc(), $cm)) {
            throw new \moodle_exception('error_notavailable', 'tool_wizards');
        }
    }

    /**
     * The screens.
     */
    protected function definition() {
        $mform = $this->_form;
        $hidden = ['cmid' => PARAM_INT, 'wizard' => PARAM_ALPHANUMEXT, 'preview' => PARAM_INT];
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
     * Render with the stepper (see {@see wizard_form::render()}).
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
            'cmid' => $this->get_cm()->id,
            'wizard' => $this->get_record()->wizardkey,
            'preview' => (int) $this->is_preview(),
        ] + $builder->draft_defaults());
    }

    /**
     * The activity's page.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return $this->get_cm()->url ?? new \moodle_url('/course/view.php', ['id' => $this->get_cm()->course]);
    }

    /**
     * The wizard's own checks, then the content handler's, each on the question it concerns.
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
        return $engine->place_errors(content_creator::check($this->doc(), $this->get_cm(), $engine->fields($answers)));
    }

    /**
     * Save the content (or, for "Try it", say what would be set).
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $engine = $this->engine();
        $answers = $engine->answers((array) $this->get_data());
        if ($this->is_preview()) {
            return ['preview' => true, 'lines' => $engine->describe($answers)];
        }
        $cm = $this->get_cm();
        $doc = $this->doc();
        $added = content_creator::save($doc, $cm, $engine->fields($answers));
        foreach ($engine->actions($answers) as [$class, $config]) {
            $class::run($config, $answers, $cm);
        }
        $handler = content_creator::handler($doc);
        $url = $handler::url($cm);
        return [
            'preview' => false,
            'added' => $added,
            'repeat' => $handler::repeatable(),
            'url' => $url ? $url->out(false) : '',
        ];
    }
}
