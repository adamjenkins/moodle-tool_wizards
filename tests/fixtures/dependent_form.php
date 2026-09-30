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

namespace tool_wizards\fixtures;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * A form whose fields depend on each other, for testing the browser emulation.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dependent_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        // A limit that only counts when switched on, like a quiz time limit.
        $mform->addElement('advcheckbox', 'limitenabled', 'Limit');
        $mform->addElement('text', 'limit', 'Limit value');
        $mform->setType('limit', PARAM_INT);
        $mform->setDefault('limit', 10);
        $mform->disabledIf('limit', 'limitenabled', 'notchecked');

        // A mode whose extra field is hidden in mode b, like completion rules.
        $mform->addElement('select', 'mode', 'Mode', ['a' => 'A', 'b' => 'B']);
        $mform->addElement('text', 'extra', 'Extra');
        $mform->setType('extra', PARAM_TEXT);
        $mform->setDefault('extra', 'x');
        $mform->hideIf('extra', 'mode', 'eq', 'b');

        // Radios and a multiple select.
        $mform->addGroup([
            $mform->createElement('radio', 'kind', '', 'One', 'one'),
            $mform->createElement('radio', 'kind', '', 'Two', 'two'),
        ], 'kindgroup', 'Kind', ' ', false);
        $mform->setDefault('kind', 'one');
        $select = $mform->addElement('select', 'tags', 'Tags', ['p' => 'P', 'q' => 'Q', 'r' => 'R']);
        $select->setMultiple(true);
        $mform->setDefault('tags', ['p']);

        $mform->addElement('editor', 'intro', 'Intro');
        $mform->setType('intro', PARAM_RAW);
    }
}
