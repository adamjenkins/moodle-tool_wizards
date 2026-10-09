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

use tool_wizards\local\wizard\packager;

/**
 * Upload wizards to import.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * The file and what to do with wizards that already exist.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement(
            'filepicker',
            'file',
            get_string('importfile', 'tool_wizards'),
            null,
            ['accepted_types' => ['.zip', '.json'], 'maxbytes' => 20 * 1024 * 1024]
        );
        $mform->addRule('file', get_string('required'), 'required', null, 'client');
        $mform->addElement('select', 'mode', get_string('importmode', 'tool_wizards'), [
            packager::COPY => get_string('importmode_copy', 'tool_wizards'),
            packager::REPLACE => get_string('importmode_replace', 'tool_wizards'),
            packager::SKIP => get_string('importmode_skip', 'tool_wizards'),
        ]);
        $mform->addHelpButton('mode', 'importmode', 'tool_wizards');
        $this->add_action_buttons(true, get_string('import', 'tool_wizards'));
    }
}
