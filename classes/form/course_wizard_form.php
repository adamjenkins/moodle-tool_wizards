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

use tool_wizards\local\course_creator;
use tool_wizards\local\wizard\engine;
use tool_wizards\local\wizard\form_builder;

/**
 * A course wizard on its own page, a screen at a time.
 *
 * Custom data: engine (the wizard's engine), preview (bool).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_wizard_form extends \moodleform {
    /**
     * The engine.
     *
     * @return engine
     */
    protected function engine(): engine {
        return $this->_customdata['engine'];
    }

    /**
     * The screens, plus "Show all settings".
     */
    protected function definition() {
        global $PAGE;
        $mform = $this->_form;
        $mform->addElement('hidden', 'wizard');
        $mform->setType('wizard', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'startcategory');
        $mform->setType('startcategory', PARAM_INT);
        $mform->addElement('hidden', 'preview');
        $mform->setType('preview', PARAM_INT);
        $preview = !empty($this->_customdata['preview']);
        $label = $preview ? get_string('preview_finish', 'tool_wizards') : get_string('createcourse', 'tool_wizards');
        (new form_builder($this->engine(), $mform))->build($label, $preview);
        if (!$preview) {
            $mform->addElement('html', \html_writer::div(\html_writer::tag(
                'button',
                get_string('showallsettings', 'tool_wizards'),
                ['type' => 'submit', 'name' => 'fullsettings', 'value' => 1, 'class' => 'btn btn-link px-0',
                'data-wizard' => 'fullsettings']
            ), 'mt-2'));
        }
        $mform->updateAttributes(['class' => trim(($mform->getAttribute('class') ?? '') . ' tool_wizards-miniwizard'
            . ' tool_wizards-coursewizardform')]);
        $PAGE->requires->js_call_amd('tool_wizards/modal_stepper', 'init', ['.tool_wizards-coursewizardform']);
    }

    /**
     * The wizard's own checks, then the course form's, each on the question it concerns.
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
        $fields = course_creator::prepare_fields($engine->fields($answers), (int) $data['startcategory']);
        if (!isset(course_creator::get_categories()[$fields->category])) {
            return [$engine->element_for($this->category_question() ?? 'category') => get_string('error_category', 'tool_wizards')];
        }
        return $engine->place_errors(course_creator::validate($fields));
    }

    /**
     * The QuickForm, for preparing the wizard's draft areas.
     *
     * @return \MoodleQuickForm
     */
    public function get_form_for_wizard(): \MoodleQuickForm {
        return $this->_form;
    }

    /**
     * The key of the question that sets the category, if any.
     *
     * @return string|null
     */
    protected function category_question(): ?string {
        return $this->engine()->field_owners()['category'] ?? null;
    }
}
