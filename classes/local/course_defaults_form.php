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

namespace tool_wizards\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/edit_form.php');

/**
 * Core's course edit form, used only to read what it would submit untouched.
 *
 * The wizard never displays or submits this form. It builds it exactly as
 * course/edit.php does, with the wizard's answers pre-set, then reads the
 * resulting default value of every element. That gives the same data a teacher
 * would send by filling in the standard form with those answers and leaving
 * everything else alone, without keeping a copy of core's defaults here.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_defaults_form extends \course_edit_form {
    /**
     * The values an untouched form would submit, in get_data() shape.
     *
     * @return array field name => value
     */
    public function get_default_data(): array {
        // Finalise the definition: this adds the course format's own options.
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }
        $mform = $this->_form;
        $data = [];
        foreach ($mform->_defaultValues as $name => $value) {
            if (!$mform->elementExists($name) && !$this->is_group_member($name)) {
                continue;
            }
            if ($name === 'sesskey' || str_starts_with($name, '_qf__') || str_starts_with($name, 'mform_isexpanded_')) {
                continue;
            }
            $data[$name] = $value;
        }
        return $data;
    }

    /**
     * Whether a default belongs to an element inside a group (for example enrolment options).
     *
     * @param string $name the element name
     * @return bool
     */
    protected function is_group_member(string $name): bool {
        foreach ($this->_form->_elements as $element) {
            if ($element instanceof \MoodleQuickForm_group) {
                foreach ($element->getElements() as $child) {
                    if ($child->getName() === $name) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
