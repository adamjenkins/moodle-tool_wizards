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

/**
 * Wizard builder, step 2: which of the target's settings the wizard asks about.
 *
 * Custom data: settings (from introspector::settings()), required (fields always asked).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_form extends \moodleform {
    /**
     * One checkbox per setting, grouped as the target's own form groups them.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('build_settings_intro', 'tool_wizards')));
        $header = null;
        $i = 0;
        foreach ($this->_customdata['settings'] as $setting) {
            if ($setting['header'] !== $header) {
                $header = $setting['header'];
                $mform->addElement('header', 'hdr' . $i++, s($header !== '' ? $header : get_string('general')));
            }
            $name = 'pick[' . $setting['field'] . ']';
            $mform->addElement('advcheckbox', $name, '', s($setting['label']) . ' '
                . \html_writer::tag('code', s($setting['field']), ['class' => 'small']));
            if (in_array($setting['field'], $this->_customdata['required'], true)) {
                $mform->setDefault($name, 1);
                $mform->freeze($name);
            }
        }
        $this->add_action_buttons(true, get_string('next', 'tool_wizards'));
    }
}
