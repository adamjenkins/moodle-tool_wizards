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
 * Choose the course to try an activity wizard in.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class try_form extends \moodleform {
    /**
     * The course chooser.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('course', 'courseid', get_string('trycourse', 'tool_wizards'), ['exclude' => [SITEID]]);
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');
        $this->add_action_buttons(true, get_string('tryit', 'tool_wizards'));
    }

    /**
     * The course must allow the wizard's activity.
     *
     * @param array $data submitted data
     * @param array $files files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $course = get_course((int) $data['courseid']);
        $modname = $this->_customdata['modname'];
        if (str_starts_with($modname, 'content:')) {
            if (!get_fast_modinfo($course)->get_instances_of(substr($modname, 8))) {
                $errors['courseid'] = get_string('tryit_noactivity', 'tool_wizards');
            }
        } else if (!\tool_wizards\local\module_creator::is_available($course, $modname)) {
            $errors['courseid'] = get_string('error_notavailable', 'tool_wizards');
        }
        return $errors;
    }
}
