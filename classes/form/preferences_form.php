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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The user's own wizard preferences.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement(
            'advcheckbox',
            'showsuggestions',
            get_string('pref_showsuggestions', 'tool_wizards'),
            get_string('pref_showsuggestions_label', 'tool_wizards')
        );
        $dismissed = $this->_customdata['dismissed'] ?? 0;
        if ($dismissed) {
            $mform->addElement(
                'advcheckbox',
                'resetcourses',
                get_string('pref_resetcourses', 'tool_wizards'),
                get_string('pref_resetcourses_label', 'tool_wizards', $dismissed)
            );
        }
        $this->add_action_buttons();
    }
}
