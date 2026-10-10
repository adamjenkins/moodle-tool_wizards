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
 * One more question in a shared question bank (a mod_qbank activity), in the bank's default
 * category or another category of that bank.
 *
 * Saved as the question bank's "Create a new question" does: question/bank/editquestion/question.php
 * with the question type's own editing form ({@see question_creator::add_to_bank()}).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbank_question extends content {
    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'qbank';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return question_creator::FIELDS + [
            'category' => 'the id of a category of this question bank (optional; the bank\'s default category)',
        ];
    }

    /**
     * The question bank's questions page.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['question-edit'];
    }

    /**
     * Adding questions.
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['moodle/question:add'];
    }

    /**
     * Once the question banks can be used, when the site offers a question type the wizard knows.
     *
     * @param \cm_info $cm the question bank
     * @return bool
     */
    public static function is_available(\cm_info $cm): bool {
        return question_creator::banks_ready() && question_creator::enabled_qtypes();
    }

    /**
     * The question types the site has switched on.
     *
     * @param \cm_info $cm the question bank
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        return ['qtype' => question_creator::enabled_qtypes()];
    }

    /**
     * Check the question, and its category if one is given.
     *
     * @param \cm_info $cm the question bank
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        global $DB;
        $errors = question_creator::check_fields($fields);
        $categoryid = (int) ($fields['category'] ?? 0);
        if ($categoryid && !$DB->record_exists('question_categories', ['id' => $categoryid, 'contextid' => $cm->context->id])) {
            $errors['category'] = get_string('question_error_category', 'tool_wizards');
        }
        return $errors;
    }

    /**
     * Create the question in the bank.
     *
     * @param \cm_info $cm the question bank
     * @param array $fields field => value
     * @return string
     */
    public static function save(\cm_info $cm, array $fields): string {
        $question = question_creator::add_to_bank(
            $cm,
            question_creator::qtype_of($fields),
            question_creator::form_fields($fields),
            (int) ($fields['category'] ?? 0)
        );
        return get_string('question_added', 'tool_wizards', $question->name);
    }

    /**
     * The question bank's questions page.
     *
     * @param \cm_info $cm the question bank
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/question/edit.php', ['cmid' => $cm->id]);
    }
}
