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

use tool_wizards\local\question_creator;

/**
 * Mini-wizard: add a quiz (mod_quiz), optionally with a first simple question.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_quiz extends add_module_base {
    /** @var int How many choices a first multiple-choice question offers. */
    const CHOICES = 4;

    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'quiz';
    }

    /**
     * Questions: a name, a description, and optionally a first question.
     */
    protected function define_questions(): void {
        global $CFG;
        // The question bank classes are not autoloaded.
        require_once($CFG->libdir . '/questionlib.php');
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('add_quiz_intro', 'tool_wizards')));
        $this->add_name_field('quiz_name');
        $this->add_description_field('quiz_description');

        $mform->addElement('header', 'firstquestionhdr', get_string('quiz_firstquestion', 'tool_wizards'));
        $mform->setExpanded('firstquestionhdr', true);
        $kinds = [
            $mform->createElement('radio', 'firstquestion', '', get_string('quiz_firstquestion_none', 'tool_wizards'), ''),
        ];
        foreach (question_creator::QTYPES as $qtype) {
            if (\question_bank::qtype_enabled($qtype)) {
                $kinds[] = $mform->createElement(
                    'radio',
                    'firstquestion',
                    '',
                    get_string('quiz_firstquestion_' . $qtype, 'tool_wizards'),
                    $qtype
                );
            }
        }
        $label = get_string('quiz_firstquestion_kind', 'tool_wizards');
        $mform->addGroup($kinds, 'firstquestiongroup', $label, \html_writer::empty_tag('br'), false);
        $mform->setDefault('firstquestion', '');

        $label = get_string('quiz_questiontext', 'tool_wizards');
        $mform->addElement('textarea', 'questiontext', $label, ['rows' => 3, 'cols' => 50]);
        $mform->setType('questiontext', PARAM_TEXT);
        $mform->hideIf('questiontext', 'firstquestion', 'eq', '');

        for ($i = 1; $i <= self::CHOICES; $i++) {
            $mform->addElement('text', 'choice' . $i, get_string('quiz_choice', 'tool_wizards', $i), ['size' => 40]);
            $mform->setType('choice' . $i, PARAM_TEXT);
            $mform->hideIf('choice' . $i, 'firstquestion', 'neq', 'multichoice');
        }
        $options = [];
        for ($i = 1; $i <= self::CHOICES; $i++) {
            $options[$i] = get_string('quiz_choice', 'tool_wizards', $i);
        }
        $mform->addElement('select', 'correctchoice', get_string('quiz_correctchoice', 'tool_wizards'), $options);
        $mform->hideIf('correctchoice', 'firstquestion', 'neq', 'multichoice');

        $tf = [
            $mform->createElement('radio', 'truefalse', '', get_string('true', 'qtype_truefalse'), 1),
            $mform->createElement('radio', 'truefalse', '', get_string('false', 'qtype_truefalse'), 0),
        ];
        $mform->addGroup($tf, 'truefalsegroup', get_string('quiz_correcttruefalse', 'tool_wizards'), ' ', false);
        $mform->setDefault('truefalse', 1);
        $mform->hideIf('truefalsegroup', 'firstquestion', 'neq', 'truefalse');
    }

    /**
     * A first question needs its text, and a multiple-choice one needs the right choice filled in plus one more.
     *
     * @param array $data submitted data
     * @param array $files uploaded files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $kind = $data['firstquestion'] ?? '';
        if ($kind === '') {
            return $errors;
        }
        if (!in_array($kind, question_creator::QTYPES, true)) {
            $errors['firstquestiongroup'] = get_string('error_noquestion', 'tool_wizards');
            return $errors;
        }
        if (trim($data['questiontext'] ?? '') === '') {
            $errors['questiontext'] = get_string('error_questiontext', 'tool_wizards');
        }
        if ($kind === 'multichoice') {
            $filled = array_filter(range(1, self::CHOICES), fn($i) => trim($data['choice' . $i] ?? '') !== '');
            $correct = (int) ($data['correctchoice'] ?? 0);
            if (!in_array($correct, $filled, true)) {
                $errors['correctchoice'] = get_string('error_correctchoice', 'tool_wizards');
            } else if (count($filled) < 2) {
                $errors['choice' . ($correct === 1 ? 2 : 1)] = get_string('error_twochoices', 'tool_wizards');
            }
        }
        return $errors;
    }

    /**
     * The module form fields.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    protected function answers_to_fields(\stdClass $data): array {
        return ['name' => $data->name, 'introeditor' => self::description_editor($data->description ?? '')];
    }

    /**
     * Add the first question, if one was asked for.
     *
     * @param \cm_info $cm the new quiz
     * @param \stdClass $data submitted data
     */
    protected function after_create(\cm_info $cm, \stdClass $data): void {
        $kind = $data->firstquestion ?? '';
        if ($kind === '') {
            return;
        }
        try {
            question_creator::add_to_quiz($cm, $kind, self::question_fields($kind, $data));
        } catch (\moodle_exception $e) {
            // The quiz exists either way; say so rather than report the whole step as failed.
            debugging('tool_wizards could not add the first question: ' . $e->getMessage(), DEBUG_DEVELOPER);
            \tool_wizards\local\prompt::mark_question_failed();
        }
    }

    /**
     * The question type form's fields for the answers.
     *
     * @param string $kind multichoice or truefalse
     * @param \stdClass $data submitted data
     * @return array
     */
    public static function question_fields(string $kind, \stdClass $data): array {
        $text = trim($data->questiontext);
        $fields = [
            'name' => shorten_text($text, 60),
            'questiontext' => ['text' => '<p>' . nl2br(s($text), false) . '</p>', 'format' => FORMAT_HTML],
        ];
        if ($kind === 'truefalse') {
            $fields['correctanswer'] = (string) (int) !empty($data->truefalse);
            return $fields;
        }
        $slot = 0;
        $fields['answer'] = [];
        $fields['fraction'] = [];
        for ($i = 1; $i <= self::CHOICES; $i++) {
            $choice = trim($data->{'choice' . $i} ?? '');
            if ($choice === '') {
                continue;
            }
            $fields['answer'][$slot] = ['text' => s($choice), 'format' => FORMAT_HTML];
            $fields['fraction'][$slot] = (int) $data->correctchoice === $i ? '1.0' : '0.0';
            $slot++;
        }
        return $fields;
    }
}
