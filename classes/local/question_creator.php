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
 * Adds a first simple question to a new quiz, the way the quiz's "Add a new question" does.
 *
 * The question type's own editing form is built as question/bank/editquestion/question.php
 * builds it for a new question in the quiz's own question bank, and submitted as a teacher
 * would who filled in only the mini-wizard's answers ({@see form_submission}). The question
 * is saved with the type's save_question() and then added to the quiz as mod/quiz/edit.php
 * adds a newly created question.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_creator {
    /** @var string[] The question types the quiz mini-wizard can add. */
    const QTYPES = ['multichoice', 'truefalse'];

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
     * Create the question and add it to the quiz.
     *
     * @param \cm_info $cm the quiz
     * @param string $qtype multichoice or truefalse
     * @param array $answers submitted field => value for the question type's own form
     * @return \stdClass the saved question
     * @throws \moodle_exception when the user may not, or the question form refuses the answers
     */
    public static function add_to_quiz(\cm_info $cm, string $qtype, array $answers): \stdClass {
        global $CFG, $DB, $USER, $PAGE;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        if (!self::can_add($cm, $qtype)) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        if (!\core_question\local\bank\question_bank_helper::has_bank_migration_task_completed_successfully()) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        $context = \core\context\module::instance($cm->id);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quiz->cmid = $cm->id;
        $PAGE->set_context($context);

        // As question/bank/editquestion/question.php prepares a new question, with the
        // quiz's own default category as the quiz's "Add a new question" link does.
        $contexts = new question_edit_contexts($context);
        $category = question_get_default_category($context->id, true);
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
            'mustbeusable' => true,
        ];
        require_capability('moodle/question:add', $context);

        $qtypeobj = \question_bank::get_qtype($qtype);
        $toform = fullclone($question);
        $toform->category = "{$category->id},{$category->contextid}";
        $toform->mdlscrollto = 0;
        $toform->appendqnumstring = 'addquestion';
        $toform->returnurl = '/mod/quiz/edit.php?cmid=' . $cm->id;
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
        $values = array_replace_recursive(form_submission::browser_values($factory()), $answers);
        [, $fromform, $errors] = form_submission::submit($factory, $values);
        if (!$fromform) {
            throw new \moodle_exception('error_questionform', 'tool_wizards', '', null, json_encode($errors));
        }

        // The rest of question.php's saving, for a new question added to a quiz.
        $fromform->modulename = 'mod_quiz';
        if (empty($fromform->usecurrentcat) && !empty($fromform->categorymoveto)) {
            $fromform->category = $fromform->categorymoveto;
        }
        if (!empty($CFG->questiondefaultssave)) {
            $qtypeobj->save_defaults_for_new_questions($fromform);
        }
        $question = $qtypeobj->save_question($question, $fromform);
        $customfieldhandler->instance_form_save($fromform);
        \question_bank::notify_question_edited($question->id);

        // And what mod/quiz/edit.php does with the new question.
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
        $quizobj->get_structure()->check_can_be_edited();
        quiz_require_question_use($question->id);
        quiz_add_quiz_question($question->id, $quiz, 0);
        quiz_delete_previews($quiz);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        return $question;
    }
}
