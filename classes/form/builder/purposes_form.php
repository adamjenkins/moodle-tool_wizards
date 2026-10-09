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
 * Wizard builder, step 4 (optional): what the activity can be for, as picture cards, and the
 * answers each purpose fills in on the later screens.
 *
 * Custom data: questions (the built questions: key, label, choices).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purposes_form extends \moodleform {
    /** @var int How many purposes the builder offers. */
    const MAX = 4;

    /**
     * Up to four purposes.
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('build_purposes_intro', 'tool_wizards')));
        $mform->addElement('text', 'purposequestion', get_string('build_purposequestion', 'tool_wizards'), ['size' => 50]);
        $mform->setType('purposequestion', PARAM_TEXT);
        $pictures = ['' => get_string('none')];
        foreach (glob($CFG->dirroot . '/admin/tool/wizards/pix/purpose/*.svg') ?: [] as $file) {
            $pictures['pix:purpose/' . basename($file, '.svg')] = basename($file, '.svg');
        }
        for ($p = 0; $p < self::MAX; $p++) {
            $mform->addElement('header', "phdr$p", get_string('build_purpose', 'tool_wizards', $p + 1));
            $mform->setExpanded("phdr$p", $p === 0);
            $mform->addElement('text', "p[$p][title]", get_string('build_purposetitle', 'tool_wizards'), ['size' => 40]);
            $mform->setType("p[$p][title]", PARAM_TEXT);
            $mform->addElement('text', "p[$p][desc]", get_string('build_purposedesc', 'tool_wizards'), ['size' => 70]);
            $mform->setType("p[$p][desc]", PARAM_TEXT);
            $mform->addElement('select', "p[$p][picture]", get_string('build_picture', 'tool_wizards'), $pictures);
            foreach ($this->_customdata['questions'] as $question) {
                if (empty($question['choices'])) {
                    continue;
                }
                $options = ['' => get_string('build_nopreset', 'tool_wizards')];
                foreach ($question['choices'] as $choice) {
                    $options[(string) $choice['value']] = $choice['title']['en'] ?? reset($choice['title']);
                }
                $mform->addElement('select', "p[$p][preset][{$question['key']}]", s(reset($question['label'])), $options);
            }
        }
        $this->add_action_buttons(true, get_string('build_save', 'tool_wizards'));
    }
}
