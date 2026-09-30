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
 * The purpose (practice, exam, pre-test or homework) picks how questions behave,
 * how many tries students get and what they see afterwards; each of those has its
 * own small screen.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_quiz extends add_module_base {
    /** @var int How many choices a first multiple-choice question offers. */
    const CHOICES = 4;

    /** @var string[] The question behaviours the wizard explains, in order. */
    const BEHAVIOURS = ['deferredfeedback', 'immediatefeedback', 'interactive'];

    /** @var string[] The review option rows (quiz form field prefixes). */
    const REVIEW_FIELDS = ['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback',
        'rightanswer', 'overallfeedback'];

    /**
     * What students may see afterwards, for each pattern: the rows shown in each review column.
     * "during" is set as well; the quiz form ignores it for behaviours that give no feedback during the attempt.
     */
    const REVIEW_PATTERNS = [
        'everything' => [
            'immediately' => self::REVIEW_FIELDS,
            'open' => self::REVIEW_FIELDS,
            'closed' => self::REVIEW_FIELDS,
        ],
        'answerslater' => [
            'immediately' => ['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback',
                'overallfeedback'],
            'open' => ['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'overallfeedback'],
            'closed' => self::REVIEW_FIELDS,
        ],
        'scoreonly' => [
            'immediately' => ['attempt', 'maxmarks', 'marks', 'overallfeedback'],
            'open' => ['attempt', 'maxmarks', 'marks', 'overallfeedback'],
            'closed' => self::REVIEW_FIELDS,
        ],
        'noanswers' => [
            'immediately' => ['attempt', 'correctness', 'maxmarks', 'marks', 'overallfeedback'],
            'open' => ['attempt', 'correctness', 'maxmarks', 'marks', 'overallfeedback'],
            'closed' => ['attempt', 'correctness', 'maxmarks', 'marks', 'overallfeedback'],
        ],
    ];

    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'quiz';
    }

    /**
     * The purposes.
     *
     * @return string[]
     */
    protected function purposes(): array {
        return ['practice', 'exam', 'pretest', 'homework'];
    }

    /**
     * The choices that suit each purpose.
     *
     * @return array
     */
    public static function presets(): array {
        return [
            'practice' => ['attempts' => 0, 'grademethod' => 1, 'preferredbehaviour' => 'interactive',
                'reviewpattern' => 'everything', 'haspass' => 0, 'questionsperpage' => 1, 'shuffleanswers' => 1,
                'completionchoice' => 'attempted'],
            'exam' => ['attempts' => 1, 'grademethod' => 1, 'preferredbehaviour' => 'deferredfeedback',
                'reviewpattern' => 'scoreonly', 'questionsperpage' => 1, 'shuffleanswers' => 1,
                'completionchoice' => 'graded'],
            'pretest' => ['attempts' => 1, 'grademethod' => 1, 'preferredbehaviour' => 'deferredfeedback',
                'reviewpattern' => 'noanswers', 'haspass' => 0, 'questionsperpage' => 0, 'shuffleanswers' => 1,
                'completionchoice' => 'attempted'],
            'homework' => ['attempts' => 3, 'grademethod' => 1, 'preferredbehaviour' => 'deferredfeedback',
                'reviewpattern' => 'answerslater', 'questionsperpage' => 1, 'shuffleanswers' => 1,
                'completionchoice' => 'attempted'],
        ];
    }

    /**
     * The quiz's own screens.
     *
     * @return string[]
     */
    protected function type_steps(): array {
        return ['timing', 'attempts', 'feedback', 'review', 'passmark', 'layout', 'firstquestion'];
    }

    /**
     * The behaviour screen needs at least two behaviours to choose from, and the first question
     * screen needs the right to add questions.
     *
     * @param string $step the step
     * @return bool
     */
    protected function type_step_applies(string $step): bool {
        if ($step === 'feedback') {
            return count($this->behaviours()) > 1;
        }
        if ($step === 'firstquestion') {
            return $this->can_add_question();
        }
        return true;
    }

    /**
     * Questions: a name, a description and the purpose.
     */
    protected function define_questions(): void {
        global $CFG;
        // The question bank classes are not autoloaded.
        require_once($CFG->libdir . '/questionlib.php');
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('add_quiz_intro', 'tool_wizards')));
        $this->add_name_field('quiz_name');
        $this->add_description_field('quiz_description');
    }

    /**
     * Timing: when it opens and closes, and any time limit.
     */
    protected function define_step_timing(): void {
        $mform = $this->_form;
        $mform->addElement('date_time_selector', 'timeopen', get_string('quiz_timeopen', 'tool_wizards'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('quiz_timeclose', 'tool_wizards'), ['optional' => true]);
        $mform->addElement('static', 'timeclosehint', '', get_string('quiz_timeclose_hint', 'tool_wizards'));
        $mform->addElement(
            'duration',
            'timelimit',
            get_string('quiz_timelimit', 'tool_wizards'),
            ['optional' => true, 'defaultunit' => MINSECS, 'units' => [MINSECS, HOURSECS]]
        );
        $mform->setDefault('timelimit', (int) get_config('quiz', 'timelimit'));
    }

    /**
     * Attempts: how many tries, and which counts.
     */
    protected function define_step_attempts(): void {
        $mform = $this->_form;
        $options = [];
        foreach ([1, 2, 3, 5] as $n) {
            $options[$n] = get_string('quiz_attempts_n', 'tool_wizards', $n);
        }
        $options[0] = get_string('quiz_attempts_unlimited', 'tool_wizards');
        $mform->addElement('select', 'attempts', get_string('quiz_attempts', 'tool_wizards'), $options);
        $default = (int) get_config('quiz', 'attempts');
        $mform->setDefault('attempts', array_key_exists($default, $options) ? $default : 0);

        $this->add_choices('grademethod', get_string('quiz_grademethod', 'tool_wizards'), [
            1 => self::choice('quiz_grademethod_highest'),
            2 => self::choice('quiz_grademethod_average'),
            3 => self::choice('quiz_grademethod_first'),
            4 => self::choice('quiz_grademethod_last'),
        ]);
        $mform->setDefault('grademethod', (int) (get_config('quiz', 'grademethod') ?: 1));
        $mform->setType('grademethod', PARAM_INT);
        $mform->hideIf('grademethodgroup', 'attempts', 'eq', 1);
    }

    /**
     * The question behaviours offered here, among those the wizard explains.
     *
     * @return string[]
     */
    protected function behaviours(): array {
        $enabled = array_keys(\question_engine::get_behaviour_options(''));
        return array_values(array_intersect(self::BEHAVIOURS, $enabled));
    }

    /**
     * Feedback: when students find out whether they were right (the question behaviour).
     */
    protected function define_step_feedback(): void {
        $mform = $this->_form;
        $choices = [];
        foreach ($this->behaviours() as $behaviour) {
            $choices[$behaviour] = [
                get_string('quiz_behaviour_' . $behaviour, 'tool_wizards'),
                get_string('quiz_behaviour_' . $behaviour . '_desc', 'tool_wizards'),
            ];
        }
        $this->add_choices('preferredbehaviour', get_string('quiz_behaviour', 'tool_wizards'), $choices);
        $mform->setType('preferredbehaviour', PARAM_ALPHA);
        $default = get_config('quiz', 'preferredbehaviour');
        if (isset($choices[$default])) {
            $mform->setDefault('preferredbehaviour', $default);
        }
    }

    /**
     * Afterwards: what students can see once they finish.
     */
    protected function define_step_review(): void {
        $mform = $this->_form;
        $choices = [];
        foreach (array_keys(self::REVIEW_PATTERNS) as $pattern) {
            $choices[$pattern] = [
                get_string('quiz_review_' . $pattern, 'tool_wizards'),
                get_string('quiz_review_' . $pattern . '_desc', 'tool_wizards'),
            ];
        }
        $this->add_choices('reviewpattern', get_string('quiz_review', 'tool_wizards'), $choices);
        $mform->setType('reviewpattern', PARAM_ALPHA);
        $mform->addElement('static', 'reviewhint', '', get_string('quiz_review_hint', 'tool_wizards'));
    }

    /**
     * Pass mark: none, or a percentage.
     */
    protected function define_step_passmark(): void {
        $mform = $this->_form;
        $this->add_choices('haspass', get_string('quiz_passmark', 'tool_wizards'), [
            0 => self::choice('quiz_passmark_none'),
            1 => self::choice('quiz_passmark_yes'),
        ]);
        $mform->setDefault('haspass', 0);
        $mform->setType('haspass', PARAM_INT);
        $mform->addElement('text', 'passpercent', get_string('quiz_passpercent', 'tool_wizards'), ['size' => 4]);
        $mform->setType('passpercent', PARAM_RAW_TRIMMED);
        $mform->setDefault('passpercent', 50);
        $mform->hideIf('passpercent', 'haspass', 'eq', 0);
    }

    /**
     * Layout: questions per page and shuffled answers.
     */
    protected function define_step_layout(): void {
        $mform = $this->_form;
        $this->add_choices('questionsperpage', get_string('quiz_perpage', 'tool_wizards'), [
            1 => self::choice('quiz_perpage_one'),
            0 => self::choice('quiz_perpage_all'),
        ]);
        $mform->setType('questionsperpage', PARAM_INT);
        $mform->setDefault('questionsperpage', (int) get_config('quiz', 'questionsperpage') === 0 ? 0 : 1);
        $mform->addElement(
            'advcheckbox',
            'shuffleanswers',
            get_string('quiz_shuffle', 'tool_wizards'),
            get_string('quiz_shuffle_label', 'tool_wizards')
        );
        $mform->setDefault('shuffleanswers', (int) get_config('quiz', 'shuffleanswers'));
    }

    /**
     * Whether the teacher may add questions to the new quiz (the capabilities question_creator
     * checks, inherited from the course into the quiz's context).
     *
     * @return bool
     */
    protected function can_add_question(): bool {
        return has_all_capabilities(['mod/quiz:manage', 'moodle/question:add'], $this->get_context_for_dynamic_submission());
    }

    /**
     * First question: none, or a multiple choice or true/false question.
     */
    protected function define_step_firstquestion(): void {
        $mform = $this->_form;
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
     * Completion when the student has attempted it, received a grade, or passed.
     *
     * @return array
     */
    protected function completion_choices(): array {
        return [
            'attempted' => [
                'label' => get_string('quiz_completion_attempted', 'tool_wizards'),
                'desc' => get_string('quiz_completion_attempted_desc', 'tool_wizards'),
                'fields' => ['completionminattemptsenabled' => 1, 'completionminattempts' => 1],
            ],
            'graded' => [
                'label' => get_string('quiz_completion_graded', 'tool_wizards'),
                'desc' => get_string('quiz_completion_graded_desc', 'tool_wizards'),
                'fields' => ['completionusegrade' => 1, 'completionpassgrade' => 0],
            ],
            'passed' => [
                'label' => get_string('quiz_completion_passed', 'tool_wizards'),
                'desc' => get_string('quiz_completion_passed_desc', 'tool_wizards'),
                'fields' => ['completionusegrade' => 1, 'completionpassgrade' => 1],
            ],
        ];
    }

    /**
     * Where core's quiz form errors belong.
     *
     * @return array
     */
    protected function error_owners(): array {
        return [
            'timeopen' => 'timeopen',
            'timeclose' => 'timeclose',
            'timelimit' => 'timelimit',
            'attempts' => 'attempts',
            'completionminattempts' => 'attempts',
            'gradepass' => 'passpercent',
            'completionpassgrade' => 'completionchoicegroup',
            'preferredbehaviour' => 'preferredbehaviourgroup',
        ];
    }

    /**
     * A pass mark is a percentage above 0; completion on passing needs one; a first question needs its text,
     * and a multiple-choice one needs the right choice filled in plus one more.
     *
     * @param array $data submitted data
     * @return array errors
     */
    protected function own_validation(array $data): array {
        $errors = [];
        $haspass = !empty($data['haspass']);
        if ($haspass) {
            $percent = unformat_float($data['passpercent'] ?? '', true);
            if ($percent === false || $percent === null || $percent <= 0 || $percent > 100) {
                $errors['passpercent'] = get_string('error_passpercent', 'tool_wizards');
            }
        }
        if (($data['completionchoice'] ?? '') === 'passed' && !$haspass) {
            $errors['completionchoicegroup'] = get_string('error_passneeded', 'tool_wizards');
        }

        $kind = $data['firstquestion'] ?? '';
        if ($kind === '') {
            return $errors;
        }
        if (!in_array($kind, question_creator::QTYPES, true) || !$this->can_add_question()) {
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
        $fields = ['name' => $data->name, 'introeditor' => self::description_editor($data->description ?? '')];
        if (in_array('timing', $this->steps, true)) {
            $fields['timeopen'] = self::date_fields((int) ($data->timeopen ?? 0));
            $fields['timeclose'] = self::date_fields((int) ($data->timeclose ?? 0));
            $fields['timelimit'] = self::duration_fields((int) ($data->timelimit ?? 0));
        }
        if (isset($data->attempts)) {
            $fields['attempts'] = (int) $data->attempts;
        }
        if (isset($data->grademethod)) {
            $fields['grademethod'] = (int) $data->grademethod;
        }
        if (!empty($data->preferredbehaviour) && in_array($data->preferredbehaviour, $this->behaviours(), true)) {
            $fields['preferredbehaviour'] = $data->preferredbehaviour;
        }
        if (!empty($data->reviewpattern) && isset(self::REVIEW_PATTERNS[$data->reviewpattern])) {
            $fields = array_replace($fields, self::review_fields($data->reviewpattern));
        }
        if (isset($data->haspass)) {
            $fields['gradepass'] = !empty($data->haspass) ? self::pass_grade((float) unformat_float($data->passpercent)) : '';
        }
        if (isset($data->questionsperpage)) {
            $fields['questionsperpage'] = (int) $data->questionsperpage;
        }
        if (isset($data->shuffleanswers)) {
            $fields['shuffleanswers'] = (int) $data->shuffleanswers;
        }
        return $fields;
    }

    /**
     * The quiz form's 32 review checkboxes for a pattern.
     *
     * @param string $pattern a key of REVIEW_PATTERNS
     * @return array field => 1 or 0
     */
    public static function review_fields(string $pattern): array {
        $fields = [];
        foreach (self::REVIEW_FIELDS as $row) {
            // During the attempt: feedback as the behaviour gives it (the quiz form locks what does not apply).
            $fields[$row . 'during'] = $row === 'overallfeedback' ? 0 : 1;
            foreach (self::REVIEW_PATTERNS[$pattern] as $when => $shown) {
                $fields[$row . $when] = in_array($row, $shown, true) ? 1 : 0;
            }
        }
        return $fields;
    }

    /**
     * A pass mark in the quiz's own grade units, from a percentage.
     *
     * @param float $percent the percentage
     * @return string
     */
    public static function pass_grade(float $percent): string {
        $max = (float) (get_config('quiz', 'maximumgrade') ?: 10);
        return format_float($max * $percent / 100, 2, false, true);
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
