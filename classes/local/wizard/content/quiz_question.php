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

namespace tool_wizards\local\wizard\content;

use tool_wizards\local\question_creator;
use tool_wizards\local\wizard\content;

/**
 * One more question in a quiz: created in the quiz's own question bank with the question type's
 * own editing form, then added to the quiz at the end, or on a new page.
 *
 * Saved as the quiz's "Add > a new question" does: question/bank/editquestion/question.php
 * creates the question and mod/quiz/edit.php adds it with quiz_add_quiz_question()
 * ({@see question_creator::add_to_quiz()}).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_question extends content {
    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'quiz';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return question_creator::FIELDS + [
            'page' => 'where it goes: end (after the last question) or new (on a new page at the end)',
        ];
    }

    /**
     * The quiz's questions page, and the quiz's question bank.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-quiz-edit', 'question-edit'];
    }

    /**
     * Managing the quiz and adding questions.
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/quiz:manage', 'moodle/question:add'];
    }

    /**
     * Only while nobody has attempted the quiz (the quiz cannot be changed then), and once the
     * question banks can be used.
     *
     * @param \cm_info $cm the quiz
     * @return bool
     */
    public static function is_available(\cm_info $cm): bool {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        return question_creator::banks_ready() && !quiz_has_attempts($cm->instance) && question_creator::enabled_qtypes();
    }

    /**
     * The question types the site has switched on.
     *
     * @param \cm_info $cm the quiz
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        return ['qtype' => question_creator::enabled_qtypes()];
    }

    /**
     * Check the question, and where it goes.
     *
     * @param \cm_info $cm the quiz
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $errors = question_creator::check_fields($fields);
        if (!in_array($fields['page'] ?? 'end', ['end', 'new'], true)) {
            $errors['page'] = get_string('question_error_page', 'tool_wizards');
        }
        if (quiz_has_attempts($cm->instance)) {
            $errors['qtype'] = get_string('question_error_attempted', 'tool_wizards');
        }
        return $errors;
    }

    /**
     * Create the question and add it to the quiz.
     *
     * @param \cm_info $cm the quiz
     * @param array $fields field => value
     * @return string
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $DB;
        $page = 0;
        if (($fields['page'] ?? 'end') === 'new') {
            // A page after the last one; quiz_add_quiz_question() puts it there.
            $lastpage = (int) $DB->get_field_sql('SELECT MAX(page) FROM {quiz_slots} WHERE quizid = ?', [$cm->instance]);
            $page = $lastpage ? $lastpage + 1 : 0;
        }
        $question = question_creator::add_to_quiz(
            $cm,
            question_creator::qtype_of($fields),
            question_creator::form_fields($fields),
            $page
        );
        return get_string('question_added', 'tool_wizards', $question->name);
    }

    /**
     * The quiz's questions page.
     *
     * @param \cm_info $cm the quiz
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id]);
    }
}
