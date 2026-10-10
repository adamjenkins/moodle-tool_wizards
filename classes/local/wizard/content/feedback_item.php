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
 * A question (or a piece of information) added at the end of a feedback survey.
 *
 * Saved as mod/feedback/edit_item.php saves a new item: the item type's class from
 * feedback_get_item_class(), its build_editform() and save_item(), then feedback_move_item().
 * The item's data is what the type's edit form's get_data() returns (the presentation strings
 * are built as in mod/feedback/item/<type>/<type>_form.php), handed over with the item class's
 * set_data(), which core's own data generator (mod/feedback/tests/generator/lib.php) uses the
 * same way.
 *
 * @package    tool_wizards
 * @copyright  2013 Ankit Agarwal
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_item extends content {
    /** @var string[] The item types this wizard offers. */
    const TYPES = ['multichoice', 'textfield', 'textarea', 'numeric', 'label'];

    /** @var string[] How students answer a multiple choice question: one, several, one from a drop-down. */
    const SUBTYPES = ['r', 'c', 'd'];

    /** @var int The longest question the item forms accept. */
    const MAXNAME = 1333;

    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'feedback';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'typ' => 'the kind of item: ' . implode(', ', self::TYPES),
            'name' => 'the question',
            'required' => '1 if students must answer it',
            'values' => 'the answers of a multichoice question, one per line',
            'subtype' => 'multichoice: r (pick one), c (pick several) or d (pick one from a drop-down list)',
            'rangefrom' => 'numeric: the smallest number allowed (empty for none)',
            'rangeto' => 'numeric: the largest number allowed (empty for none)',
            'text' => 'label: the information shown (an editor value: text, format, itemid)',
        ];
    }

    /**
     * The feedback's Questions page.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-feedback-edit'];
    }

    /**
     * Editing the questions needs mod/feedback:edititems (mod/feedback/edit.php, edit_item.php).
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/feedback:edititems'];
    }

    /**
     * A number from a number question, or null when there is none.
     *
     * @param mixed $value the value
     * @return float|null|false false when it is not a number
     */
    protected static function number($value) {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return unformat_float((string) $value, true);
    }

    /**
     * The answers of a multichoice question, one per line, without empty lines.
     *
     * @param array $fields field => value
     * @return string[]
     */
    protected static function values(array $fields): array {
        $lines = array_map('trim', preg_split('/\R/', (string) ($fields['values'] ?? '')));
        return array_values(array_filter($lines, fn($line) => $line !== ''));
    }

    /**
     * A known type; a question (or, for information, a text); answers for multiple choice;
     * numbers for a number's range.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        $errors = [];
        $typ = (string) ($fields['typ'] ?? '');
        if (!in_array($typ, self::TYPES, true)) {
            $errors['typ'] = get_string('feedbackquestion_error_typ', 'tool_wizards');
            return $errors;
        }
        if ($typ === 'label') {
            $text = $fields['text'] ?? null;
            if (!is_array($text) || html_is_blank((string) ($text['text'] ?? ''))) {
                $errors['text'] = get_string('feedbackquestion_error_text', 'tool_wizards');
            }
            return $errors;
        }
        $name = trim((string) ($fields['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = get_string('feedbackquestion_error_name', 'tool_wizards');
        } else if (\core_text::strlen($name) > self::MAXNAME) {
            $errors['name'] = get_string('maximumchars', '', self::MAXNAME);
        }
        if ($typ === 'multichoice') {
            if (!self::values($fields)) {
                $errors['values'] = get_string('feedbackquestion_error_values', 'tool_wizards');
            }
            if (isset($fields['subtype']) && !in_array((string) $fields['subtype'], self::SUBTYPES, true)) {
                $errors['subtype'] = get_string('feedbackquestion_error_typ', 'tool_wizards');
            }
        }
        if ($typ === 'numeric') {
            foreach (['rangefrom', 'rangeto'] as $bound) {
                if (self::number($fields[$bound] ?? null) === false) {
                    $errors[$bound] = get_string('err_numeric', 'form');
                }
            }
        }
        return $errors;
    }

    /**
     * Add the item at the end of the feedback.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return string
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/feedback/lib.php');

        $feedback = $DB->get_record('feedback', ['id' => $cm->instance], '*', MUST_EXIST);
        $typ = (string) $fields['typ'];
        $itemobj = feedback_get_item_class($typ);
        // As edit_item.php: a new item has position -1 until its form puts it last.
        $itemobj->build_editform((object) ['id' => null, 'position' => -1, 'typ' => $typ, 'options' => ''], $feedback, $cm);

        $item = (object) [
            'id' => 0,
            'feedback' => $feedback->id,
            'template' => 0,
            'name' => $typ === 'label' ? 'label' : trim((string) $fields['name']),
            'label' => '',
            'presentation' => '',
            'typ' => $typ,
            'hasvalue' => 0,
            'position' => $DB->count_records('feedback_item', ['feedback' => $feedback->id]) + 1,
            'required' => $typ === 'label' || empty($fields['required']) ? 0 : 1,
            'dependitem' => 0,
            'dependvalue' => '',
            'options' => '',
        ];
        switch ($typ) {
            case 'multichoice':
                // Built the way the multiple choice item's edit form builds it in get_data.
                $subtype = (string) ($fields['subtype'] ?? '');
                $subtype = in_array($subtype, self::SUBTYPES, true) ? $subtype : 'r';
                $item->presentation = $subtype . FEEDBACK_MULTICHOICE_TYPE_SEP
                    . implode(FEEDBACK_MULTICHOICE_LINE_SEP, self::values($fields));
                $item->hidenoselect = 1;
                $item->ignoreempty = 0;
                break;
            case 'textfield':
                // The size and length a new short answer gets (feedback_item_textfield::build_editform()).
                $item->presentation = '30|255';
                break;
            case 'textarea':
                // The width and height a new longer answer gets (feedback_item_textarea::build_editform()).
                $item->presentation = '30|5';
                break;
            case 'numeric':
                // As feedback_numeric_form::get_data(): "-" for no limit, the smaller number first.
                $from = self::number($fields['rangefrom'] ?? null);
                $to = self::number($fields['rangeto'] ?? null);
                $from = $from === null || $from === false ? '-' : $from;
                $to = $to === null || $to === false ? '-' : $to;
                if ($from !== '-' && $to !== '-' && $from > $to) {
                    [$from, $to] = [$to, $from];
                }
                $item->presentation = $from . '|' . $to;
                break;
            case 'label':
                $text = $fields['text'];
                $item->presentation_editor = [
                    'text' => (string) ($text['text'] ?? ''),
                    'format' => (int) ($text['format'] ?? FORMAT_HTML),
                    'itemid' => (int) ($text['itemid'] ?? 0),
                ];
                break;
        }
        $itemobj->set_data($item);
        $saved = $itemobj->save_item();
        feedback_move_item($saved, $saved->position);

        if ($typ === 'label') {
            return get_string('feedbackquestion_saved_label', 'tool_wizards');
        }
        $name = format_string($saved->name, true, ['context' => $cm->context, 'escape' => false]);
        return get_string('feedbackquestion_saved', 'tool_wizards', shorten_text($name, 80));
    }

    /**
     * The feedback's Questions page.
     *
     * @param \cm_info $cm the activity
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/feedback/edit.php', ['id' => $cm->id]);
    }
}
