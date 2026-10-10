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

use core_question\local\bank\question_edit_contexts;

/**
 * Creates questions the way the question bank's "Create a new question" does, in a quiz's own
 * question bank (then adding them to the quiz) or in a shared question bank.
 *
 * The question type's own editing form is built as question/bank/editquestion/question.php
 * builds it for a new question, and submitted as a teacher would who filled in only the
 * wizard's answers ({@see form_submission}). The question is saved with the type's
 * save_question(), and a quiz question is then added to the quiz as mod/quiz/edit.php adds a
 * newly created question.
 *
 * The in-activity question wizards describe a question with a few simple fields
 * ({@see self::FIELDS}); {@see self::form_fields()} turns them into the type's own form fields
 * and {@see self::check_fields()} checks them first.
 *
 * Adapted from Moodle core question/bank/editquestion/question.php and mod/quiz/edit.php.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_creator {
    /** @var string[] The question types the quiz mini-wizard can add. */
    const QTYPES = ['multichoice', 'truefalse'];

    /** @var string[] The question types the in-activity question wizards can add. */
    const WIZARD_QTYPES = ['multichoice', 'truefalse', 'shortanswer', 'numerical', 'match', 'essay', 'description'];

    /** @var string[] How students may answer an essay: wizard value => the essay's response format. */
    const RESPONSE_FORMATS = ['editor' => 'editor', 'plain' => 'plain', 'file' => 'noinline'];

    /** @var int The most numbered answer slots a wizard question has. */
    const MAXSLOTS = 10;

    /**
     * @var array The simple fields of the question wizards, for the content handlers' fields().
     */
    const FIELDS = [
        'qtype' => 'question type: multichoice, truefalse, shortanswer, numerical, match, essay or description',
        'single' => 'multiple choice: 1 one right answer, 0 several right answers',
        'name' => 'name in the question bank (optional; the start of the question text when empty)',
        'questiontext' => 'the question, or for a description the text students read (editor, or plain text)',
        'choice' => 'multiple choice: the answers to choose from, choice[1] to choice[10] (plain text)',
        'rightchoice' => 'multiple choice with one right answer: the number of the right choice',
        'right' => 'multiple choice with several right answers: right[n] = 1 when choice[n] is right',
        'correctanswer' => 'true/false: 1 when the statement is true, 0 when false',
        'accept' => 'short answer: the answers accepted as right, accept[1] to accept[10] (* matches anything)',
        'casesensitive' => 'short answer: 1 when capital letters must match',
        'number' => 'numerical: the right number',
        'tolerance' => 'numerical: also accept numbers this far either side of it (default 0)',
        'item' => 'matching: the items students match, item[1] to item[10]',
        'match' => 'matching: what each item matches, match[1] to match[10]; a match without an item is an extra wrong answer',
        'responseformat' => 'essay: editor (text with formatting), plain (plain text) or file (upload a file)',
        'responselines' => 'essay: the height of the answer box in lines (2, 3, 5, 10, ... 40)',
        'maxwords' => 'essay: the most words students may write (0 or empty: no limit)',
        'graderinfo' => 'essay: notes for whoever marks it (plain text)',
        'feedbackright' => 'feedback for a right answer (plain text, optional)',
        'feedbackwrong' => 'feedback for a wrong answer (plain text, optional)',
        'mark' => 'the marks the question is worth (default 1; not for a description)',
    ];

    /** @var array Number of right (or wrong) answers => each one's share of the marks, as the form offers it. */
    const SHARES = [1 => '1.0', 2 => '0.5', 3 => '0.3333333', 4 => '0.25', 5 => '0.2', 6 => '0.1666667',
        7 => '0.1428571', 8 => '0.125', 9 => '0.1111111', 10 => '0.1'];

    /**
     * Whether the current user can add a first question of this type to the quiz.
     *
     * @param \cm_info $cm the quiz
     * @param string $qtype the question type
     * @return bool
     */
    public static function can_add(\cm_info $cm, string $qtype): bool {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        if (!in_array($qtype, self::QTYPES, true) || !\question_bank::qtype_enabled($qtype)) {
            return false;
        }
        $context = \core\context\module::instance($cm->id);
        return has_all_capabilities(['mod/quiz:manage', 'moodle/question:add'], $context);
    }

    /**
     * The question types the wizards can add that the site has switched on.
     *
     * @return string[]
     */
    public static function enabled_qtypes(): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        return array_values(array_filter(self::WIZARD_QTYPES, fn($qtype) => \question_bank::qtype_enabled($qtype)));
    }

    /**
     * Whether question banks can be used yet (after the upgrade to Moodle 5.0, once the
     * question bank migration has finished).
     *
     * @return bool
     */
    public static function banks_ready(): bool {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        return \core_question\local\bank\question_bank_helper::has_bank_migration_task_completed_successfully();
    }

    /**
     * Create the question and add it to the quiz.
     *
     * @param \cm_info $cm the quiz
     * @param string $qtype one of {@see self::WIZARD_QTYPES}
     * @param array $answers submitted field => value for the question type's own form
     * @param int $page the quiz page to add it to (0: the last page, as the quiz does by default)
     * @return \stdClass the saved question
     * @throws \moodle_exception when the user may not, or the question form refuses the answers
     */
    public static function add_to_quiz(\cm_info $cm, string $qtype, array $answers, int $page = 0): \stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $context = \core\context\module::instance($cm->id);
        if (
            !in_array($qtype, self::WIZARD_QTYPES, true) || !\question_bank::qtype_enabled($qtype)
                || !has_all_capabilities(['mod/quiz:manage', 'moodle/question:add'], $context)
        ) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quiz->cmid = $cm->id;
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
        // Checked before the question is created, so a quiz with attempts gets no stray question.
        $quizobj->get_structure()->check_can_be_edited();

        $question = self::create($cm, $qtype, $answers, 0, '/mod/quiz/edit.php?cmid=' . $cm->id, true);

        // And what mod/quiz/edit.php does with the new question.
        quiz_require_question_use($question->id);
        quiz_add_quiz_question($question->id, $quiz, $page);
        quiz_delete_previews($quiz);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        return $question;
    }

    /**
     * Create the question in a shared question bank.
     *
     * @param \cm_info $cm the question bank (mod_qbank)
     * @param string $qtype one of {@see self::WIZARD_QTYPES}
     * @param array $answers submitted field => value for the question type's own form
     * @param int $categoryid a category of this bank, or 0 for its default category
     * @return \stdClass the saved question
     * @throws \moodle_exception when the user may not, or the question form refuses the answers
     */
    public static function add_to_bank(\cm_info $cm, string $qtype, array $answers, int $categoryid = 0): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $context = \core\context\module::instance($cm->id);
        if (
            !in_array($qtype, self::WIZARD_QTYPES, true) || !\question_bank::qtype_enabled($qtype)
                || !has_capability('moodle/question:add', $context)
        ) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        return self::create($cm, $qtype, $answers, $categoryid, '/question/edit.php?cmid=' . $cm->id, false);
    }

    /**
     * Create a question in the question bank of an activity (a quiz's own bank, or a shared bank),
     * as question/bank/editquestion/question.php does.
     *
     * @param \cm_info $cm the activity whose bank it goes in
     * @param string $qtype the question type
     * @param array $answers submitted field => value for the question type's own form
     * @param int $categoryid a category in that bank, or 0 for its default category
     * @param string $returnurl where the question form would return to
     * @param bool $forquiz whether the question is being added to the quiz straight away
     * @return \stdClass the saved question
     * @throws \moodle_exception when the bank cannot be used, or the question form refuses the answers
     */
    protected static function create(
        \cm_info $cm,
        string $qtype,
        array $answers,
        int $categoryid,
        string $returnurl,
        bool $forquiz
    ): \stdClass {
        global $CFG, $DB, $USER, $PAGE;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/editlib.php');

        if (!self::banks_ready()) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        $context = \core\context\module::instance($cm->id);
        $PAGE->set_context($context);
        if (!$PAGE->has_set_url()) {
            // The question form's editors read the page URL, as on question/bank/editquestion/question.php.
            $PAGE->set_url(new \moodle_url($returnurl));
        }

        // As question/bank/editquestion/question.php prepares a new question, in the bank's default
        // category as the "Create a new question" button does, or in a category of that bank.
        $contexts = new question_edit_contexts($context);
        if ($categoryid) {
            $category = $DB->get_record('question_categories', ['id' => $categoryid, 'contextid' => $context->id]);
            if (!$category) {
                throw new \moodle_exception('error_noquestion', 'tool_wizards');
            }
        } else {
            $category = question_get_default_category($context->id, true);
        }
        $question = new \stdClass();
        $question->category = $category->id;
        $question->qtype = $qtype;
        $question->createdby = $USER->id;
        $question->contextid = $category->contextid;
        $question->formoptions = (object) [
            'canedit' => question_has_capability_on($question, 'edit'),
            'canmove' => question_has_capability_on($question, 'move') && has_capability('moodle/question:add', $context),
            'cansaveasnew' => false,
            'repeatelements' => true,
            'mustbeusable' => $forquiz,
        ];
        require_capability('moodle/question:add', $context);

        $qtypeobj = \question_bank::get_qtype($qtype);
        $toform = fullclone($question);
        $toform->category = "{$category->id},{$category->contextid}";
        $toform->mdlscrollto = 0;
        $toform->appendqnumstring = $forquiz ? 'addquestion' : '';
        $toform->returnurl = $returnurl;
        $toform->makecopy = 0;
        $toform->idnumber = null;
        $toform->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $toform->cmid = $cm->id;
        $toform->courseid = $cm->course;
        $toform->inpopup = 0;
        $customfieldhandler = \qbank_customfields\customfield\question_handler::create();
        $customfieldhandler->instance_form_before_set_data($toform);

        $factory = function () use ($qtypeobj, $question, $category, $contexts, $toform) {
            $form = $qtypeobj->create_editing_form('question.php', clone $question, $category, $contexts, true);
            $form->set_data(clone $toform);
            return $form;
        };
        $values = form_submission::browser_values($factory(), $answers);
        [, $fromform, $errors] = form_submission::submit($factory, $values);
        if (!$fromform) {
            throw new \moodle_exception('error_questionform', 'tool_wizards', '', null, json_encode($errors));
        }

        // The rest of question.php's saving, for a new question.
        if ($forquiz) {
            $fromform->modulename = 'mod_quiz';
        }
        if (empty($fromform->usecurrentcat) && !empty($fromform->categorymoveto)) {
            $fromform->category = $fromform->categorymoveto;
        }
        if (!empty($CFG->questiondefaultssave)) {
            $qtypeobj->save_defaults_for_new_questions($fromform);
        }
        $question = $qtypeobj->save_question($question, $fromform);
        $customfieldhandler->instance_form_save($fromform);
        \question_bank::notify_question_edited($question->id);

        return $question;
    }

    /**
     * The question type of the wizard's fields.
     *
     * @param array $fields the wizard's fields ({@see self::FIELDS})
     * @return string
     */
    public static function qtype_of(array $fields): string {
        return is_string($fields['qtype'] ?? null) ? $fields['qtype'] : '';
    }

    /**
     * Check the wizard's fields before anything is saved.
     *
     * @param array $fields the wizard's fields ({@see self::FIELDS})
     * @return array field => error message
     */
    public static function check_fields(array $fields): array {
        $qtype = self::qtype_of($fields);
        if (!in_array($qtype, self::enabled_qtypes(), true)) {
            return ['qtype' => get_string('error_noquestion', 'tool_wizards')];
        }
        $errors = [];
        if (!self::editor_value($fields['questiontext'] ?? '')) {
            $errors['questiontext'] = get_string(
                $qtype === 'description' ? 'question_error_text' : 'error_questiontext',
                'tool_wizards'
            );
        }
        if ($qtype !== 'description' && !self::is_blank($fields['mark'] ?? '')) {
            $mark = self::number($fields['mark']);
            if ($mark === null || $mark < 0) {
                $errors['mark'] = get_string('question_error_mark', 'tool_wizards');
            }
        }
        switch ($qtype) {
            case 'multichoice':
                $choices = self::slots($fields['choice'] ?? []);
                $rights = self::right_choices($fields);
                if (count($choices) < 2) {
                    $errors['choice'] = get_string('error_twochoices', 'tool_wizards');
                } else if (array_diff($rights, array_keys($choices))) {
                    $errors[self::is_single($fields) ? 'rightchoice' : 'right'] = get_string('error_correctchoice', 'tool_wizards');
                } else if (!$rights) {
                    $errors[self::is_single($fields) ? 'rightchoice' : 'right'] =
                        get_string('question_error_noright', 'tool_wizards');
                }
                break;
            case 'shortanswer':
                if (!self::slots($fields['accept'] ?? [])) {
                    $errors['accept'] = get_string('question_error_noaccept', 'tool_wizards');
                }
                break;
            case 'numerical':
                if (self::is_blank($fields['number'] ?? '') || self::number($fields['number']) === null) {
                    $errors['number'] = get_string('question_error_number', 'tool_wizards');
                }
                if (!self::is_blank($fields['tolerance'] ?? '')) {
                    $tolerance = self::number($fields['tolerance']);
                    if ($tolerance === null || $tolerance < 0) {
                        $errors['tolerance'] = get_string('question_error_tolerance', 'tool_wizards');
                    }
                }
                break;
            case 'match':
                $items = self::slots($fields['item'] ?? []);
                $matches = self::slots($fields['match'] ?? []);
                if (array_diff_key($items, $matches)) {
                    $errors['match'] = get_string('question_error_matchmissing', 'tool_wizards');
                } else if (count($items) < 2) {
                    $errors['item'] = get_string('question_error_twopairs', 'tool_wizards');
                } else if (count($matches) < 3) {
                    $errors['match'] = get_string('question_error_threematches', 'tool_wizards');
                }
                break;
            case 'essay':
                $format = $fields['responseformat'] ?? 'editor';
                if (!is_string($format) || !isset(self::RESPONSE_FORMATS[$format])) {
                    $errors['responseformat'] = get_string('question_error_responseformat', 'tool_wizards');
                }
                if (!self::is_blank($fields['maxwords'] ?? '')) {
                    $words = self::number($fields['maxwords']);
                    if ($words === null || $words < 0 || $words != (int) $words) {
                        $errors['maxwords'] = get_string('question_error_maxwords', 'tool_wizards');
                    }
                }
                break;
        }
        return $errors;
    }

    /**
     * The question type's own form fields for the wizard's fields, as a teacher would fill in
     * that form. Fields the wizard does not set keep the form's defaults.
     *
     * @param array $fields the wizard's fields ({@see self::FIELDS}), already checked
     * @return array form field => value, nested like $_POST
     */
    public static function form_fields(array $fields): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $qtype = self::qtype_of($fields);
        $text = self::editor_value($fields['questiontext'] ?? '') ?? ['text' => '', 'format' => FORMAT_HTML];
        $form = ['name' => self::name_for($fields, $text), 'questiontext' => $text];
        if ($qtype !== 'description') {
            // Always say: the question form would otherwise start from the mark this teacher last used.
            $form['defaultmark'] = self::is_blank($fields['mark'] ?? '') ? '1' : self::number_string($fields['mark']);
        }
        $right = self::editor_value($fields['feedbackright'] ?? '');
        $wrong = self::editor_value($fields['feedbackwrong'] ?? '');

        switch ($qtype) {
            case 'multichoice':
                // As qtype_multichoice_edit_form: one right answer gets 100%; with several, the right
                // ones share 100% and the wrong ones share -100%, so ticking everything scores nothing.
                $single = self::is_single($fields);
                $choices = self::slots($fields['choice'] ?? []);
                $rights = array_values(array_intersect(self::right_choices($fields), array_keys($choices)));
                $nright = count($rights);
                $nwrong = count($choices) - $nright;
                $form['single'] = $single ? '1' : '0';
                $slot = 0;
                foreach ($choices as $n => $choice) {
                    $form['answer'][$slot] = ['text' => s($choice), 'format' => FORMAT_HTML];
                    if (in_array($n, $rights, true)) {
                        $form['fraction'][$slot] = $single ? '1.0' : self::SHARES[$nright];
                    } else {
                        $form['fraction'][$slot] = $single || !$nwrong ? '0.0' : '-' . self::SHARES[$nwrong];
                    }
                    $slot++;
                }
                $form['noanswers'] = (string) max(5, QUESTION_NUMANS_START, $slot);
                self::combined_feedback($form, $right, $wrong);
                break;

            case 'truefalse':
                $true = (int) ($fields['correctanswer'] ?? 1) === 1;
                $form['correctanswer'] = $true ? '1' : '0';
                if ($right) {
                    $form[$true ? 'feedbacktrue' : 'feedbackfalse'] = $right;
                }
                if ($wrong) {
                    $form[$true ? 'feedbackfalse' : 'feedbacktrue'] = $wrong;
                }
                break;

            case 'shortanswer':
                $slot = 0;
                foreach (self::slots($fields['accept'] ?? []) as $accept) {
                    $form['answer'][$slot] = $accept;
                    $form['fraction'][$slot] = '1.0';
                    if ($right) {
                        $form['feedback'][$slot] = $right;
                    }
                    $slot++;
                }
                if ($wrong) {
                    // A last answer "*" matches anything else: the usual way to give feedback for wrong answers.
                    $form['answer'][$slot] = '*';
                    $form['fraction'][$slot] = '0.0';
                    $form['feedback'][$slot] = $wrong;
                    $slot++;
                }
                $form['usecase'] = empty($fields['casesensitive']) ? '0' : '1';
                $form['noanswers'] = (string) max(QUESTION_NUMANS_START, $slot);
                break;

            case 'numerical':
                \question_bank::get_qtype('numerical');
                $form['answer'][0] = self::number_string($fields['number'] ?? '');
                $form['tolerance'][0] = self::is_blank($fields['tolerance'] ?? '') ? '0'
                    : self::number_string($fields['tolerance']);
                $form['fraction'][0] = '1.0';
                if ($right) {
                    $form['feedback'][0] = $right;
                }
                if ($wrong) {
                    $form['answer'][1] = '*';
                    $form['tolerance'][1] = '0';
                    $form['fraction'][1] = '0.0';
                    $form['feedback'][1] = $wrong;
                }
                // No units (the form's default may be a teacher's saved preference).
                $form['unitrole'] = (string) \qtype_numerical::UNITNONE;
                break;

            case 'match':
                $items = self::slots($fields['item'] ?? []);
                $slot = 0;
                // The pairs first, then the extra answers that match no item.
                foreach ([true, false] as $paired) {
                    foreach (self::slots($fields['match'] ?? []) as $n => $match) {
                        if (isset($items[$n]) !== $paired) {
                            continue;
                        }
                        $form['subquestions'][$slot] = ['text' => $paired ? s($items[$n]) : '', 'format' => FORMAT_HTML];
                        $form['subanswers'][$slot] = $match;
                        $slot++;
                    }
                }
                $form['noanswers'] = (string) max(QUESTION_NUMANS_START, $slot);
                self::combined_feedback($form, $right, $wrong);
                break;

            case 'essay':
                $format = self::RESPONSE_FORMATS[$fields['responseformat'] ?? 'editor'] ?? 'editor';
                $form['responseformat'] = $format;
                if ($format === 'noinline') {
                    // Students upload one file; the essay form requires it to be required.
                    $form['attachments'] = '1';
                    $form['attachmentsrequired'] = '1';
                } else {
                    $form['responserequired'] = '1';
                    if (!self::is_blank($fields['responselines'] ?? '')) {
                        $form['responsefieldlines'] = (string) (int) $fields['responselines'];
                    }
                    $words = (int) self::number($fields['maxwords'] ?? '');
                    if ($words > 0) {
                        $form['maxwordenabled'] = '1';
                        $form['maxwordlimit'] = (string) $words;
                    }
                }
                $graderinfo = self::editor_value($fields['graderinfo'] ?? '');
                if ($graderinfo) {
                    $form['graderinfo'] = $graderinfo;
                }
                break;
        }
        return $form;
    }

    /**
     * The question's name: the one given, or the start of its text.
     *
     * @param array $fields the wizard's fields
     * @param array $text the question text as an editor value
     * @return string
     */
    public static function name_for(array $fields, array $text): string {
        $name = is_scalar($fields['name'] ?? null) ? trim((string) $fields['name']) : '';
        if ($name === '') {
            $plain = trim(preg_replace('/\s+/u', ' ', html_to_text((string) $text['text'], 0, false)));
            $name = shorten_text($plain, 60);
        }
        if ($name === '') {
            $qtype = self::qtype_of($fields);
            $name = in_array($qtype, self::WIZARD_QTYPES, true) ? get_string('pluginname', 'qtype_' . $qtype)
                : get_string('question_defaultname', 'tool_wizards');
        }
        return \core_text::substr($name, 0, 255);
    }

    /**
     * Set the combined feedback for a right and a wrong answer, keeping the form's defaults otherwise.
     *
     * @param array $form the form fields, changed in place
     * @param array|null $right feedback for a right answer
     * @param array|null $wrong feedback for a wrong answer
     */
    protected static function combined_feedback(array &$form, ?array $right, ?array $wrong): void {
        if ($right) {
            $form['correctfeedback'] = $right;
        }
        if ($wrong) {
            $form['incorrectfeedback'] = $wrong;
        }
    }

    /**
     * Whether a multiple-choice question has one right answer (the default).
     *
     * @param array $fields the wizard's fields
     * @return bool
     */
    protected static function is_single(array $fields): bool {
        return (int) ($fields['single'] ?? 1) === 1;
    }

    /**
     * The numbers of the right choices of a multiple-choice question.
     *
     * @param array $fields the wizard's fields
     * @return int[]
     */
    protected static function right_choices(array $fields): array {
        if (self::is_single($fields)) {
            $n = (int) ($fields['rightchoice'] ?? 0);
            return $n > 0 ? [$n] : [];
        }
        $out = [];
        foreach ((array) ($fields['right'] ?? []) as $n => $on) {
            if (!empty($on)) {
                $out[] = (int) $n;
            }
        }
        sort($out);
        return $out;
    }

    /**
     * The filled-in numbered slots of a field (choice[1], choice[2], ...), in order.
     *
     * @param mixed $value the field's value
     * @return array slot number => trimmed text
     */
    protected static function slots($value): array {
        $out = [];
        foreach (is_array($value) ? $value : [] as $n => $text) {
            if (is_scalar($text) && trim((string) $text) !== '' && (int) $n >= 1 && (int) $n <= self::MAXSLOTS) {
                $out[(int) $n] = trim((string) $text);
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * A plain-text or editor value as an editor value, or null when it is empty.
     *
     * @param mixed $value plain text, or ['text' => ..., 'format' => ..., 'itemid' => ...]
     * @return array|null
     */
    public static function editor_value($value): ?array {
        if (is_array($value)) {
            $text = (string) ($value['text'] ?? '');
            if (html_is_blank($text)) {
                return null;
            }
            $out = ['text' => $text, 'format' => (int) ($value['format'] ?? FORMAT_HTML)];
            if (!empty($value['itemid'])) {
                // The wizard's own draft area: the question form saves its files from there.
                $out['itemid'] = (int) $value['itemid'];
            }
            return $out;
        }
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text === '') {
            return null;
        }
        return ['text' => '<p>' . nl2br(s($text), false) . '</p>', 'format' => FORMAT_HTML];
    }

    /**
     * Whether a value is empty.
     *
     * @param mixed $value the value
     * @return bool
     */
    protected static function is_blank($value): bool {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * A number from a field (a number, or a string in the user's or the plain format).
     *
     * @param mixed $value the value
     * @return float|null null when it is not a number
     */
    protected static function number($value): ?float {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $number = unformat_float(trim($value), true);
        return $number === false || $number === null ? null : (float) $number;
    }

    /**
     * A number as the question forms take it.
     *
     * @param mixed $value the value
     * @return string
     */
    protected static function number_string($value): string {
        $number = self::number($value);
        return $number === null ? (is_scalar($value) ? trim((string) $value) : '') : format_float($number, -1, false, true);
    }
}
