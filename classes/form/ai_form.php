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

/**
 * "Improve with AI": what the admin wants changed.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_form extends \moodleform {
    /**
     * The instruction, or the AI policy to accept first.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'ai', 1);
        $mform->setType('ai', PARAM_INT);
        if (empty($this->_customdata['policyaccepted'])) {
            $mform->addElement('html', \html_writer::div(get_string('userpolicy', 'core_ai'), 'border rounded p-3 mb-3'));
            $mform->addElement('advcheckbox', 'acceptpolicy', '', get_string('acceptai', 'core_ai'));
            $mform->addRule('acceptpolicy', get_string('required'), 'required', null, 'client');
        }
        $mform->addElement(
            'textarea',
            'instruction',
            get_string('ai_instruction', 'tool_wizards'),
            ['rows' => 3, 'cols' => 70, 'placeholder' => get_string('ai_instruction_placeholder', 'tool_wizards')]
        );
        $mform->setType('instruction', PARAM_TEXT);
        $mform->addRule('instruction', get_string('required'), 'required', null, 'client');
        $mform->addElement('submit', 'askai', get_string('ai_ask', 'tool_wizards'));
    }

    /**
     * The policy must be accepted.
     *
     * @param array $data submitted data
     * @param array $files files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['policyaccepted']) && empty($data['acceptpolicy'])) {
            $errors['acceptpolicy'] = get_string('required');
        }
        return $errors;
    }
}
