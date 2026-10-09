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

namespace tool_wizards\form\builder;

use tool_wizards\local\wizard\introspector;

/**
 * Wizard builder, step 1: what the wizard creates, and its name.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class target_form extends \moodleform {
    /**
     * The fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('build_target_intro', 'tool_wizards')));
        $targets = ['course' => get_string('target_course', 'tool_wizards')] + introspector::modules();
        $mform->addElement('select', 'target', get_string('build_target', 'tool_wizards'), $targets);
        $mform->setDefault('target', 'quiz');
        $mform->addElement('text', 'title', get_string('build_title', 'tool_wizards'), ['size' => 50]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->addElement('static', 'titlehint', '', get_string('build_title_hint', 'tool_wizards'));
        $mform->addElement('text', 'description', get_string('build_description', 'tool_wizards'), ['size' => 70]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('advcheckbox', 'shared', '', get_string('build_shared', 'tool_wizards'));
        $mform->setDefault('shared', 1);
        $mform->hideIf('shared', 'target', 'eq', 'course');
        $this->add_action_buttons(true, get_string('next', 'tool_wizards'));
    }

    /**
     * Activity forms are read in a course, so the site needs one.
     *
     * @param array $data submitted data
     * @param array $files files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['target'] !== 'course' && !introspector::sample_course()) {
            $errors['target'] = get_string('build_nocourse', 'tool_wizards');
        }
        return $errors;
    }
}
