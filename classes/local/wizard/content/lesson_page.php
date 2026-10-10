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
 * A page of a lesson: a content page with buttons, a question page, or the end of a branch.
 *
 * Pages are saved with lesson_page::create(), with the data mod/lesson/editpage.php passes it from
 * the page forms in mod/lesson/pagetypes/*.php (answer_editor[], response_editor[], jumpto[], score[]).
 * The end of a branch follows lesson_add_page_form_endofbranch::construction_override() in
 * mod/lesson/pagetypes/endofbranch.php: it goes back to the nearest content page above it.
 *
 * Jumps are given by name: "next" (the next page), "this" (stay and try again), "previous"
 * (the page before) and "end" (the end of the lesson). New pages go at the end of the lesson,
 * or at its start.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_page extends content {
    /** @var string[] the kinds of page, in the order teachers see them */
    const KINDS = ['content', 'multichoice', 'truefalse', 'shortanswer', 'numerical', 'matching', 'essay', 'endofbranch'];

    /** @var string[] the kinds that are questions */
    const QUESTIONS = ['multichoice', 'truefalse', 'shortanswer', 'numerical', 'matching', 'essay'];

    /** @var string[] the jump names */
    const JUMPS = ['next', 'this', 'previous', 'end'];

    /** @var int the longest answer a short answer page takes (its form's maxlength) */
    const MAXSHORTANSWER = 200;

    /**
     * The activity module.
     *
     * @return string
     */
    public static function modname(): string {
        return 'lesson';
    }

    /**
     * The fields a wizard may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'kind' => 'the kind of page: content, multichoice, truefalse, shortanswer, numerical, matching, essay or '
                . 'endofbranch',
            'title' => 'the page title (not for endofbranch)',
            'contents' => 'content page: what students read (editor)',
            'question' => 'question pages: the question (editor)',
            'position' => 'where the page goes: end (the default) or start; endofbranch only at the end',
            'button' => 'content page: the buttons\' labels, button[1], button[2], ...',
            'goto' => 'content page: where each button leads, goto[1], goto[2], ...: next, previous, end or this '
                . '(default next)',
            'choice' => 'multichoice: the answers, choice[1], choice[2], ...',
            'correct' => 'multichoice: the number of the right answer',
            'truefalse' => 'truefalse: 1 if the statement is true, 0 if it is false',
            'accept' => 'shortanswer: the answers that count as right, accept[1], accept[2], ...',
            'number' => 'numerical: the right number',
            'margin' => 'numerical: how far from it an answer may be and still count (optional)',
            'item' => 'matching: the items, item[1], item[2], ...',
            'match' => 'matching: what each item matches, match[1], match[2], ...',
            'rightjump' => 'after a right answer: next or end (default next)',
            'rightfeedback' => 'what students read after a right answer (optional)',
            'wrongjump' => 'after a wrong answer: this, next, previous or end (default this)',
            'wrongfeedback' => 'what students read after a wrong answer (optional)',
            'afterjump' => 'essay: where students go after answering: next or end (default next)',
        ];
    }

    /**
     * The lesson's page editing page (core sends teachers there from an empty lesson's view.php too).
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-lesson-edit'];
    }

    /**
     * What editpage.php asks for.
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/lesson:edit'];
    }

    /**
     * The kinds of page this lesson can take now: the end of a branch only after a content page.
     *
     * @param \cm_info $cm the lesson
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        $kinds = array_values(array_diff(self::KINDS, ['endofbranch']));
        if (self::branch_table((int) $cm->instance)) {
            $kinds[] = 'endofbranch';
        }
        return ['kind' => $kinds];
    }

    /**
     * Check the fields.
     *
     * @param \cm_info $cm the lesson
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        $lesson = self::lesson($cm);
        $kind = (string) ($fields['kind'] ?? '');
        if (!in_array($kind, self::KINDS, true)) {
            return ['kind' => get_string('lessonpage_error_kind', 'tool_wizards')];
        }
        $errors = [];
        $position = (string) ($fields['position'] ?? 'end');
        if (!in_array($position, ['end', 'start'], true)) {
            $errors['position'] = get_string('lessonpage_error_position', 'tool_wizards');
        }
        foreach (['rightjump', 'wrongjump', 'afterjump'] as $field) {
            if (isset($fields[$field]) && !in_array((string) $fields[$field], self::JUMPS, true)) {
                $errors[$field] = get_string('lessonpage_error_jump', 'tool_wizards');
            }
        }
        if ($kind === 'endofbranch') {
            if ($position !== 'end') {
                $errors['position'] = get_string('lessonpage_error_endofbranchstart', 'tool_wizards');
            } else if (!self::branch_table((int) $lesson->id)) {
                $errors['kind'] = get_string('lessonpage_error_nobranch', 'tool_wizards');
            }
            return $errors;
        }

        $title = self::plain($fields['title'] ?? '');
        if ($title === '') {
            $errors['title'] = get_string('required');
        } else if (\core_text::strlen($title) > 255) {
            $errors['title'] = get_string('maximumchars', '', 255);
        }
        if (in_array($kind, self::QUESTIONS, true) && self::editor($fields['question'] ?? '')['text'] === '') {
            $errors['question'] = get_string('required');
        }

        $max = (int) $lesson->maxanswers;
        $toomany = get_string('lessonpage_error_toomany', 'tool_wizards', $max);
        switch ($kind) {
            case 'content':
                $buttons = self::slots($fields['button'] ?? []);
                if (!$buttons) {
                    $errors['button'] = get_string('lessonpage_error_button', 'tool_wizards');
                } else if (count($buttons) > $max) {
                    $errors['button'] = $toomany;
                }
                foreach ((array) ($fields['goto'] ?? []) as $goto) {
                    if (!in_array((string) $goto, self::JUMPS, true)) {
                        $errors['goto'] = get_string('lessonpage_error_jump', 'tool_wizards');
                    }
                }
                break;
            case 'multichoice':
                $choices = self::slots($fields['choice'] ?? []);
                if (count($choices) < 2) {
                    $errors['choice'] = get_string('lessonpage_error_twochoices', 'tool_wizards');
                } else if (count($choices) > $max) {
                    $errors['choice'] = $toomany;
                }
                if (!isset($choices[(int) ($fields['correct'] ?? 0)])) {
                    $errors['correct'] = get_string('lessonpage_error_correct', 'tool_wizards');
                }
                break;
            case 'truefalse':
                if (!in_array((string) ($fields['truefalse'] ?? ''), ['0', '1'], true)) {
                    $errors['truefalse'] = get_string('required');
                }
                break;
            case 'shortanswer':
                $accept = self::slots($fields['accept'] ?? []);
                if (!$accept) {
                    $errors['accept'] = get_string('lessonpage_error_accept', 'tool_wizards');
                } else if (count($accept) > $max) {
                    $errors['accept'] = $toomany;
                }
                foreach ($accept as $answer) {
                    if (\core_text::strlen($answer) > self::MAXSHORTANSWER) {
                        $errors['accept'] = get_string('maximumchars', '', self::MAXSHORTANSWER);
                    }
                }
                break;
            case 'numerical':
                if (self::number($fields['number'] ?? '') === null) {
                    $errors['number'] = get_string('err_numeric', 'form');
                }
                $margin = $fields['margin'] ?? '';
                if (trim((string) $margin) !== '' && ((self::number($margin) ?? -1) < 0)) {
                    $errors['margin'] = get_string('lessonpage_error_margin', 'tool_wizards');
                }
                break;
            case 'matching':
                $items = self::slots($fields['item'] ?? []);
                $matches = self::slots($fields['match'] ?? []);
                if (array_diff_key($items, $matches) || array_diff_key($matches, $items)) {
                    $errors['match'] = get_string('lessonpage_error_pair', 'tool_wizards');
                } else if (count($items) < 2) {
                    $errors['item'] = get_string('lessonpage_error_twopairs', 'tool_wizards');
                } else if (count($items) > $max) {
                    $errors['item'] = $toomany;
                }
                break;
        }

        // Without custom scoring, Moodle counts an answer as right when it moves students forward
        // (lesson::jumpto_is_correct() in mod/lesson/locallib.php).
        if (in_array($kind, self::QUESTIONS, true) && $kind !== 'essay' && empty($lesson->custom)) {
            if (!in_array((string) ($fields['rightjump'] ?? 'next'), ['next', 'end'], true)) {
                $errors['rightjump'] = get_string('lessonpage_error_rightback', 'tool_wizards');
            }
            if (in_array((string) ($fields['wrongjump'] ?? 'this'), ['next', 'end'], true)) {
                $errors['wrongjump'] = get_string('lessonpage_error_wrongforward', 'tool_wizards');
            }
        }
        return $errors;
    }

    /**
     * Save the page.
     *
     * @param \cm_info $cm the lesson
     * @param array $fields field => value
     * @return string what was added
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $DB;
        $lesson = self::lesson($cm);
        $kind = (string) $fields['kind'];
        $atstart = ($fields['position'] ?? 'end') === 'start' && $kind !== 'endofbranch';

        $data = new \stdClass();
        // As in editpage.php: the page the new one goes after, 0 for the start.
        $data->pageid = $atstart ? 0 : self::last_page((int) $lesson->id);
        $data->qtype = self::qtype($kind);
        $data->title = self::plain($fields['title'] ?? '');
        $data->contents_editor = self::editor($fields[$kind === 'content' ? 'contents' : 'question'] ?? '');
        $data->answer_editor = [];
        $data->response_editor = [];
        $data->jumpto = [];
        $data->score = [];

        $right = self::jump($fields['rightjump'] ?? 'next');
        $wrong = self::jump($fields['wrongjump'] ?? 'this');
        $rightfeedback = self::editor($fields['rightfeedback'] ?? '');
        $wrongfeedback = self::editor($fields['wrongfeedback'] ?? '');

        switch ($kind) {
            case 'content':
                // The content page form (lesson_add_page_form_branchtable): buttons are plain text,
                // and the two checkboxes are ticked by default.
                $data->layout = 1;
                $data->display = 1;
                $gotos = (array) ($fields['goto'] ?? []);
                foreach (self::slots($fields['button'] ?? []) as $n => $label) {
                    $data->answer_editor[] = $label;
                    $data->jumpto[] = self::jump($gotos[$n] ?? 'next');
                }
                break;
            case 'multichoice':
                $correct = (int) $fields['correct'];
                foreach (self::slots($fields['choice'] ?? []) as $n => $choice) {
                    $isright = $n === $correct;
                    $data->answer_editor[] = ['text' => s($choice), 'format' => FORMAT_HTML];
                    $data->response_editor[] = $isright ? $rightfeedback : $wrongfeedback;
                    $data->jumpto[] = $isright ? $right : $wrong;
                    $data->score[] = $isright ? 1 : 0;
                }
                break;
            case 'truefalse':
                // The form's first answer is the right one, the second the wrong one.
                $true = ['text' => s(get_string('lessonpage_true', 'tool_wizards')), 'format' => FORMAT_HTML];
                $false = ['text' => s(get_string('lessonpage_false', 'tool_wizards')), 'format' => FORMAT_HTML];
                $istrue = (string) $fields['truefalse'] === '1';
                $data->answer_editor = [$istrue ? $true : $false, $istrue ? $false : $true];
                $data->response_editor = [$rightfeedback, $wrongfeedback];
                $data->jumpto = [$right, $wrong];
                $data->score = [1, 0];
                break;
            case 'shortanswer':
            case 'numerical':
                if ($kind === 'shortanswer') {
                    $answers = array_values(self::slots($fields['accept'] ?? []));
                } else {
                    $number = self::number($fields['number']);
                    $margin = abs((float) (self::number($fields['margin'] ?? '') ?? 0));
                    $answers = [$margin > 0 ? ($number - $margin) . ':' . ($number + $margin) : (string) $number];
                }
                foreach ($answers as $answer) {
                    $data->answer_editor[] = $answer;
                    $data->response_editor[] = $rightfeedback;
                    $data->jumpto[] = $right;
                    $data->score[] = 1;
                }
                // The "all other answers" slot (the forms' enableotheranswers) comes after the last answer.
                $data->enableotheranswers = 1;
                $data->response_editor[] = $wrongfeedback;
                $data->jumpto[] = $wrong;
                $data->score[] = 0;
                break;
            case 'matching':
                // The matching form: answers 0 and 1 hold the feedback and jumps, the pairs follow.
                $data->answer_editor = [$rightfeedback, $wrongfeedback];
                $data->jumpto = [$right, $wrong];
                $data->score = [1, 0];
                $matches = self::slots($fields['match'] ?? []);
                $i = 2;
                foreach (self::slots($fields['item'] ?? []) as $n => $item) {
                    $data->answer_editor[$i] = ['text' => s($item), 'format' => FORMAT_HTML];
                    $data->response_editor[$i] = clean_param($matches[$n], PARAM_NOTAGS);
                    $i++;
                }
                break;
            case 'essay':
                $data->jumpto = [self::jump($fields['afterjump'] ?? 'next')];
                $data->score = [1];
                break;
            case 'endofbranch':
                // As construction_override(): a fixed title and text, and a jump back to the content page.
                $data->title = get_string('endofbranch', 'lesson');
                $data->contents_editor = ['text' => get_string('endofbranch', 'lesson'), 'format' => FORMAT_HTML];
                $data->jumpto = [self::branch_table((int) $lesson->id)];
                break;
        }

        $maxbytes = (int) get_course($cm->course)->maxbytes;
        $page = \lesson_page::create($data, $lesson, $cm->context, $maxbytes);

        $number = 1;
        $id = $DB->get_field('lesson_pages', 'prevpageid', ['id' => $page->id]);
        while ($id) {
            $number++;
            $id = $DB->get_field('lesson_pages', 'prevpageid', ['id' => $id]);
        }
        return get_string('lessonpage_added', 'tool_wizards', ['number' => $number, 'title' => $data->title]);
    }

    /**
     * Where the teacher sees the pages.
     *
     * @param \cm_info $cm the lesson
     * @return \moodle_url
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/lesson/edit.php', ['id' => $cm->id]);
    }

    /**
     * The lesson, with its page types loaded (they define the LESSON_PAGE_* constants).
     *
     * @param \cm_info $cm the lesson
     * @return \lesson
     */
    protected static function lesson(\cm_info $cm): \lesson {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/lesson/locallib.php');
        $lesson = new \lesson($DB->get_record('lesson', ['id' => $cm->instance], '*', MUST_EXIST), $cm);
        \lesson_page_type_manager::get($lesson);
        return $lesson;
    }

    /**
     * The lesson page type of a kind.
     *
     * @param string $kind the kind
     * @return int
     */
    protected static function qtype(string $kind): int {
        $types = [
            'content' => LESSON_PAGE_BRANCHTABLE,
            'multichoice' => LESSON_PAGE_MULTICHOICE,
            'truefalse' => LESSON_PAGE_TRUEFALSE,
            'shortanswer' => LESSON_PAGE_SHORTANSWER,
            'numerical' => LESSON_PAGE_NUMERICAL,
            'matching' => LESSON_PAGE_MATCHING,
            'essay' => LESSON_PAGE_ESSAY,
            'endofbranch' => LESSON_PAGE_ENDOFBRANCH,
        ];
        return (int) $types[$kind];
    }

    /**
     * The jump value of a jump name.
     *
     * @param mixed $name next, this, previous or end
     * @return int
     */
    protected static function jump($name): int {
        switch ((string) $name) {
            case 'this':
                return LESSON_THISPAGE;
            case 'previous':
                return LESSON_PREVIOUSPAGE;
            case 'end':
                return LESSON_EOL;
        }
        return LESSON_NEXTPAGE;
    }

    /**
     * The last page of a lesson.
     *
     * @param int $lessonid the lesson
     * @return int its id, 0 if the lesson has no pages
     */
    protected static function last_page(int $lessonid): int {
        global $DB;
        $ids = $DB->get_fieldset_select('lesson_pages', 'id', 'lessonid = ? AND nextpageid = 0', [$lessonid]);
        return $ids ? (int) reset($ids) : 0;
    }

    /**
     * The content page an end of branch added at the end would go back to: the nearest one
     * above the last page, as lesson_add_page_form_endofbranch::construction_override() finds it.
     *
     * @param int $lessonid the lesson
     * @return int its id, 0 if there is none
     */
    protected static function branch_table(int $lessonid): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/lesson/locallib.php');
        require_once($CFG->dirroot . '/mod/lesson/pagetypes/branchtable.php');
        $pages = $DB->get_records('lesson_pages', ['lessonid' => $lessonid], '', 'id, prevpageid, qtype');
        $id = self::last_page($lessonid);
        $seen = [];
        while ($id && isset($pages[$id]) && !isset($seen[$id])) {
            if ((int) $pages[$id]->qtype === (int) LESSON_PAGE_BRANCHTABLE) {
                return $id;
            }
            $seen[$id] = true;
            $id = (int) $pages[$id]->prevpageid;
        }
        return 0;
    }

    /**
     * The filled-in slots of a numbered field (button[1], button[2], ...), in order.
     *
     * @param mixed $values number => text
     * @return array number => trimmed plain text
     */
    protected static function slots($values): array {
        $out = [];
        foreach ((array) $values as $n => $value) {
            $text = self::plain($value);
            if ($text !== '') {
                $out[(int) $n] = $text;
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * A one-line text, cleaned as the page forms clean their text fields.
     *
     * @param mixed $value the value
     * @return string
     */
    protected static function plain($value): string {
        return is_scalar($value) ? trim(clean_param((string) $value, PARAM_TEXT)) : '';
    }

    /**
     * An editor value from an editor answer, or plain text.
     *
     * @param mixed $value ['text' => ..., 'format' => ..., 'itemid' => ...] or a string
     * @return array text (trimmed, '' when nothing is in it), format, and itemid if given
     */
    protected static function editor($value): array {
        if (is_array($value)) {
            $text = (string) ($value['text'] ?? '');
            $editor = ['text' => \tool_wizards\local\wizard\engine::is_empty($value) ? '' : $text,
                'format' => (int) ($value['format'] ?? FORMAT_HTML)];
            if (!empty($value['itemid'])) {
                $editor['itemid'] = (int) $value['itemid'];
            }
            return $editor;
        }
        $text = is_scalar($value) ? trim((string) $value) : '';
        return ['text' => $text === '' ? '' : '<p>' . nl2br(s($text), false) . '</p>', 'format' => FORMAT_HTML];
    }

    /**
     * A number typed by the teacher, in their own notation.
     *
     * @param mixed $value the value
     * @return float|null null when it is not a number
     */
    protected static function number($value): ?float {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }
        $number = unformat_float(trim((string) $value), true);
        return ($number === false || $number === null) ? null : (float) $number;
    }
}
