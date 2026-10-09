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
 * Wizard builder, step 3: how each chosen setting is asked, in plain language, and on which screen.
 *
 * Custom data: settings (the chosen ones).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions_form extends \moodleform {
    /**
     * A block per setting.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('build_questions_intro', 'tool_wizards')));
        foreach ($this->_customdata['settings'] as $i => $setting) {
            $mform->addElement('header', "qhdr$i", s($setting['label']));
            $mform->setExpanded("qhdr$i", true);
            $mform->addElement('text', "q[$i][label]", get_string('build_question', 'tool_wizards'), ['size' => 60]);
            $mform->setType("q[$i][label]", PARAM_TEXT);
            $mform->addElement('text', "q[$i][help]", get_string('build_help', 'tool_wizards'), ['size' => 70]);
            $mform->setType("q[$i][help]", PARAM_TEXT);
            $kinds = [];
            foreach (introspector::kinds_for($setting) as $kind) {
                $kinds[$kind] = get_string('kind_' . $kind, 'tool_wizards');
            }
            $mform->addElement('select', "q[$i][kind]", get_string('build_kind', 'tool_wizards'), $kinds);
            $mform->addElement('text', "q[$i][screen]", get_string('build_screen', 'tool_wizards'), ['size' => 30]);
            $mform->setType("q[$i][screen]", PARAM_TEXT);
            $mform->addElement('advcheckbox', "q[$i][required]", '', get_string('build_required', 'tool_wizards'));
            $c = 0;
            foreach ($setting['choices'] as $value => $optionlabel) {
                $mform->addElement('text', "q[$i][choices][$c][title]", get_string(
                    'build_choice',
                    'tool_wizards',
                    s($optionlabel)
                ), ['size' => 40]);
                $mform->setType("q[$i][choices][$c][title]", PARAM_TEXT);
                $mform->addElement(
                    'text',
                    "q[$i][choices][$c][desc]",
                    get_string('build_choicedesc', 'tool_wizards'),
                    ['size' => 60]
                );
                $mform->setType("q[$i][choices][$c][desc]", PARAM_TEXT);
                $mform->addElement('advcheckbox', "q[$i][choices][$c][offer]", '', get_string('build_offer', 'tool_wizards'));
                $c++;
            }
        }
        $this->add_action_buttons(true, get_string('next', 'tool_wizards'));
    }

    /**
     * Starting values: the form's own labels and grouping.
     *
     * @param array $settings the chosen settings
     * @return array
     */
    public static function defaults(array $settings): array {
        $values = [];
        foreach ($settings as $i => $setting) {
            $values["q[$i][label]"] = $setting['label'];
            $values["q[$i][kind]"] = introspector::kinds_for($setting)[0];
            $values["q[$i][screen]"] = $setting['header'] !== '' ? $setting['header'] : get_string('general');
            $values["q[$i][required]"] = !empty($setting['required']) || in_array($setting['field'], ['name', 'fullname'], true)
                ? 1 : 0;
            $c = 0;
            foreach ($setting['choices'] as $label) {
                $values["q[$i][choices][$c][title]"] = $label;
                $values["q[$i][choices][$c][offer]"] = 1;
                $c++;
            }
        }
        return $values;
    }
}
