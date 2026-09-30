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
 * Mini-wizard: add a glossary (mod_glossary).
 *
 * The purpose (course vocabulary, a glossary the students build, a class FAQ, or a
 * collection of links) picks who writes entries, whether they are checked first, how
 * entries look and whether terms link automatically.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_glossary extends add_module_base {
    /** @var string[] The display formats the wizard explains (format directory names). */
    const FORMATS = ['dictionary', 'fullwithauthor', 'encyclopedia', 'faq'];

    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'glossary';
    }

    /**
     * The purposes.
     *
     * @return string[]
     */
    protected function purposes(): array {
        return ['vocabulary', 'shared', 'faq', 'links'];
    }

    /**
     * The choices that suit each purpose.
     *
     * @return array
     */
    public static function presets(): array {
        return [
            'vocabulary' => ['defaultapproval' => 0, 'editalways' => 0, 'displayformat' => 'dictionary',
                'allowcomments' => 0, 'allowduplicatedentries' => 0, 'usedynalink' => 1, 'gradingchoice' => 'none',
                'completionchoice' => 'view'],
            'shared' => ['defaultapproval' => 1, 'editalways' => 1, 'displayformat' => 'fullwithauthor',
                'allowcomments' => 1, 'allowduplicatedentries' => 0, 'usedynalink' => 0, 'gradingchoice' => 'none',
                'completionchoice' => 'entries3'],
            'faq' => ['defaultapproval' => 0, 'editalways' => 0, 'displayformat' => 'faq',
                'allowcomments' => 1, 'allowduplicatedentries' => 0, 'usedynalink' => 0, 'gradingchoice' => 'none',
                'completionchoice' => self::COMPLETION_DEFAULT],
            'links' => ['defaultapproval' => 1, 'editalways' => 1, 'displayformat' => 'encyclopedia',
                'allowcomments' => 1, 'allowduplicatedentries' => 1, 'usedynalink' => 0, 'gradingchoice' => 'none',
                'completionchoice' => 'entries1'],
        ];
    }

    /**
     * The glossary's own screens.
     *
     * @return string[]
     */
    protected function type_steps(): array {
        return ['entries', 'look', 'extras', 'linking', 'grading'];
    }

    /**
     * Auto-linking only means something when the site's glossary filter is on.
     *
     * @param string $step the step
     * @return bool
     */
    protected function type_step_applies(string $step): bool {
        if ($step === 'linking') {
            return filter_is_enabled('glossary');
        }
        return true;
    }

    /**
     * Questions: a name, a description and the purpose.
     */
    protected function define_questions(): void {
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('add_glossary_intro', 'tool_wizards')));
        $this->add_name_field('glossary_name');
        $this->add_description_field('glossary_description');
    }

    /**
     * Entries: approval before they appear, and how long authors can edit them.
     */
    protected function define_step_entries(): void {
        $mform = $this->_form;
        $this->add_choices('defaultapproval', get_string('glossary_approval', 'tool_wizards'), [
            1 => self::choice('glossary_approval_auto'),
            0 => self::choice('glossary_approval_check'),
        ]);
        $mform->setType('defaultapproval', PARAM_INT);
        $mform->setDefault('defaultapproval', (int) get_config('core', 'glossary_defaultapproval'));
        $mform->addElement(
            'advcheckbox',
            'editalways',
            get_string('glossary_editalways', 'tool_wizards'),
            get_string('glossary_editalways_label', 'tool_wizards')
        );
    }

    /**
     * Look: the display format.
     */
    protected function define_step_look(): void {
        $mform = $this->_form;
        $choices = [];
        foreach (self::FORMATS as $format) {
            $choices[$format] = [get_string('glossary_format_' . $format, 'tool_wizards'),
                get_string('glossary_format_' . $format . '_desc', 'tool_wizards')];
        }
        $this->add_choices('displayformat', get_string('glossary_step_look', 'tool_wizards'), $choices);
        $mform->setType('displayformat', PARAM_ALPHA);
        $mform->setDefault('displayformat', 'dictionary');
    }

    /**
     * Extras: comments (where the site allows them) and repeated terms.
     */
    protected function define_step_extras(): void {
        global $CFG;
        $mform = $this->_form;
        if (!empty($CFG->usecomments)) {
            $mform->addElement(
                'advcheckbox',
                'allowcomments',
                get_string('glossary_comments', 'tool_wizards'),
                get_string('glossary_comments_label', 'tool_wizards')
            );
            $mform->setDefault('allowcomments', (int) get_config('core', 'glossary_allowcomments'));
        }
        $mform->addElement(
            'advcheckbox',
            'allowduplicatedentries',
            get_string('glossary_duplicates', 'tool_wizards'),
            get_string('glossary_duplicates_label', 'tool_wizards')
        );
        $mform->setDefault('allowduplicatedentries', (int) get_config('core', 'glossary_dupentries'));
    }

    /**
     * Linking: whether terms become links wherever they appear in the course.
     */
    protected function define_step_linking(): void {
        $mform = $this->_form;
        $mform->addElement(
            'advcheckbox',
            'usedynalink',
            get_string('glossary_linking', 'tool_wizards'),
            get_string('glossary_linking_label', 'tool_wizards')
        );
        $mform->setDefault('usedynalink', (int) get_config('core', 'glossary_linkbydefault'));
        $mform->addElement('static', 'linkinghint', '', get_string('glossary_linking_hint', 'tool_wizards'));
    }

    /**
     * Grading: none, or rated entries.
     */
    protected function define_step_grading(): void {
        $mform = $this->_form;
        $this->add_choices('gradingchoice', get_string('glossary_step_grading', 'tool_wizards'), [
            'none' => self::choice('glossary_grading_none'),
            'ratings' => self::choice('glossary_grading_ratings'),
        ]);
        $mform->setType('gradingchoice', PARAM_ALPHA);
        $mform->setDefault('gradingchoice', 'none');
        $this->add_max_grade_field('gradingchoice', 'none');
    }

    /**
     * Completion by viewing, or by adding entries. Each choice sets the entries rule itself:
     * a new glossary has "add 1 entry" ticked already.
     *
     * @return array
     */
    protected function completion_choices(): array {
        return [
            'view' => [
                'label' => get_string('completion_view', 'tool_wizards'),
                'desc' => get_string('glossary_completion_view_desc', 'tool_wizards'),
                'fields' => ['completionview' => 1, 'completionentriesenabled' => 0],
            ],
            'entries1' => [
                'label' => get_string('glossary_completion_entries', 'tool_wizards', 1),
                'desc' => get_string('glossary_completion_entries_desc', 'tool_wizards'),
                'fields' => ['completionentriesenabled' => 1, 'completionentries' => 1],
            ],
            'entries3' => [
                'label' => get_string('glossary_completion_entries', 'tool_wizards', 3),
                'desc' => get_string('glossary_completion_entries_desc', 'tool_wizards'),
                'fields' => ['completionentriesenabled' => 1, 'completionentries' => 3],
            ],
        ];
    }

    /**
     * Where core's glossary form errors belong.
     *
     * @return array
     */
    protected function error_owners(): array {
        return ['scale' => 'maxgrade', 'completionentries' => 'completionchoicegroup', 'displayformat' => 'displayformatgroup'];
    }

    /**
     * A grade needs a maximum.
     *
     * @param array $data submitted data
     * @return array errors
     */
    protected function own_validation(array $data): array {
        return $this->max_grade_errors($data, 'gradingchoice', 'none');
    }

    /**
     * The module form fields.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    protected function answers_to_fields(\stdClass $data): array {
        global $CFG;
        require_once($CFG->dirroot . '/rating/lib.php');
        $fields = ['name' => $data->name, 'introeditor' => self::description_editor($data->description ?? '')];
        foreach (['defaultapproval', 'editalways', 'allowcomments', 'allowduplicatedentries', 'usedynalink'] as $name) {
            if (isset($data->$name)) {
                $fields[$name] = (int) $data->$name;
            }
        }
        if (!empty($data->displayformat) && in_array($data->displayformat, self::FORMATS, true)) {
            $fields['displayformat'] = $data->displayformat;
        }
        if (($data->gradingchoice ?? 'none') === 'ratings') {
            $fields['assessed'] = RATING_AGGREGATE_AVERAGE;
            $fields['scale'] = ['modgrade_type' => 'point', 'modgrade_point' => (string) (int) ($data->maxgrade ?? 10)];
        } else {
            $fields['assessed'] = 0;
        }
        return $fields;
    }
}
