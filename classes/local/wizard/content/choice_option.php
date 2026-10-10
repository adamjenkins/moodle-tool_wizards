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

use tool_wizards\local\wizard\content;

/**
 * One more option (answer) added to a choice, without touching its other options or the
 * answers students have already given.
 *
 * Saved as choice_update_instance() in mod/choice/lib.php inserts a new option (a choice_options
 * row with its text, limit and time). The rest of that function rewrites the whole choice from its
 * settings form, which a single new option does not need; the course_module_updated event that
 * the settings form's save triggers (course/modlib.php) is triggered here too, so logs and
 * observers see the change.
 *
 * @package    tool_wizards
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choice_option extends content {
    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'choice';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'text' => 'the option\'s text',
            'limit' => 'how many students may choose it (used when the choice limits answers)',
        ];
    }

    /**
     * The choice's own page.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-choice-view'];
    }

    /**
     * A limit is asked only when the choice limits the number of answers per option.
     *
     * @param \cm_info $cm the activity
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        return ['limitanswers' => [self::choice($cm)->limitanswers ? '1' : '0']];
    }

    /**
     * The choice.
     *
     * @param \cm_info $cm the activity
     * @return \stdClass
     */
    protected static function choice(\cm_info $cm): \stdClass {
        global $DB;
        return $DB->get_record('choice', ['id' => $cm->instance], '*', MUST_EXIST);
    }

    /**
     * The option's text, cleaned as the choice settings form cleans it (PARAM_CLEANHTML).
     *
     * @param array $fields field => value
     * @return string
     */
    protected static function text(array $fields): string {
        return trim(clean_param((string) ($fields['text'] ?? ''), PARAM_CLEANHTML));
    }

    /**
     * Some text, not already an option of this choice, and a whole number as a limit.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        global $DB;
        $errors = [];
        $text = self::text($fields);
        if ($text === '') {
            $errors['text'] = get_string('choiceoption_error_text', 'tool_wizards');
        } else {
            foreach ($DB->get_fieldset('choice_options', 'text', ['choiceid' => $cm->instance]) as $existing) {
                if (\core_text::strtolower(trim($existing)) === \core_text::strtolower($text)) {
                    $errors['text'] = get_string('choiceoption_error_exists', 'tool_wizards');
                    break;
                }
            }
        }
        $limit = trim((string) ($fields['limit'] ?? ''));
        if ($limit !== '' && (!preg_match('/^\d+$/', $limit))) {
            $errors['limit'] = get_string('choiceoption_error_limit', 'tool_wizards');
        }
        return $errors;
    }

    /**
     * Add the option.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return string
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $DB;
        $choice = self::choice($cm);
        $now = time();

        $option = new \stdClass();
        $option->choiceid = $choice->id;
        $option->text = self::text($fields);
        $option->maxanswers = (int) ($fields['limit'] ?? 0);
        $option->timemodified = $now;
        $DB->insert_record('choice_options', $option);

        $DB->set_field('choice', 'timemodified', $now, ['id' => $choice->id]);
        \core\event\course_module_updated::create_from_cm($cm, $cm->context)->trigger();

        $text = format_string($option->text, true, ['context' => $cm->context, 'escape' => false]);
        return get_string('choiceoption_saved', 'tool_wizards', $text);
    }
}
