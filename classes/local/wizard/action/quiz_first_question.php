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

namespace tool_wizards\local\wizard\action;

use tool_wizards\local\question_creator;

/**
 * Add a first multiple choice or true/false question to a new quiz.
 *
 * Config: "kind" (question key: '' none, multichoice, truefalse), "text" (question key),
 * "choices" (list of question keys), "correct" (question key: number of the right choice),
 * "truefalse" (question key: 1 true, 0 false).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_first_question extends \tool_wizards\local\wizard\action {
    /**
     * Check the config.
     *
     * @param array $config the action object
     * @param string[] $questionkeys the definition's question keys
     * @return string[]
     */
    public static function check_config(array $config, array $questionkeys): array {
        $problems = [];
        foreach (['kind', 'text', 'correct', 'truefalse'] as $name) {
            if (!in_array($config[$name] ?? null, $questionkeys, true)) {
                $problems[] = "\"$name\" must be the key of a question";
            }
        }
        if (!is_array($config['choices'] ?? null) || array_diff($config['choices'], $questionkeys)) {
            $problems[] = '"choices" must be a list of question keys';
        }
        $unknown = array_diff(array_keys($config), ['action', 'when', 'kind', 'text', 'choices', 'correct', 'truefalse']);
        if ($unknown) {
            $problems[] = 'unknown property ' . implode(', ', $unknown);
        }
        return $problems;
    }

    /**
     * A first question needs its text; a multiple-choice one needs its right choice filled in plus one more.
     *
     * @param array $config the action object
     * @param array $answers question key => answer
     * @param \context $context the course context
     * @return array
     */
    public static function validate(array $config, array $answers, \context $context): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $kind = (string) ($answers[$config['kind']] ?? '');
        if ($kind === '') {
            return [];
        }
        if (
            !in_array($kind, question_creator::QTYPES, true) || !\question_bank::qtype_enabled($kind)
                || !has_all_capabilities(['mod/quiz:manage', 'moodle/question:add'], $context)
        ) {
            return [$config['kind'] => get_string('error_noquestion', 'tool_wizards')];
        }
        if (trim((string) ($answers[$config['text']] ?? '')) === '') {
            return [$config['text'] => get_string('error_questiontext', 'tool_wizards')];
        }
        if ($kind === 'multichoice') {
            $filled = [];
            foreach ($config['choices'] as $i => $key) {
                if (trim((string) ($answers[$key] ?? '')) !== '') {
                    $filled[] = $i + 1;
                }
            }
            $correct = (int) ($answers[$config['correct']] ?? 0);
            if (!in_array($correct, $filled, true)) {
                return [$config['correct'] => get_string('error_correctchoice', 'tool_wizards')];
            }
            if (count($filled) < 2) {
                $other = $config['choices'][$correct === 1 ? 1 : 0];
                return [$other => get_string('error_twochoices', 'tool_wizards')];
            }
        }
        return [];
    }

    /**
     * Add the question. A failure is reported on the course page; the quiz exists either way.
     *
     * @param array $config the action object
     * @param array $answers question key => answer
     * @param \cm_info|\stdClass $created the new quiz
     */
    public static function run(array $config, array $answers, $created): void {
        $kind = (string) ($answers[$config['kind']] ?? '');
        if ($kind === '' || !$created instanceof \cm_info) {
            return;
        }
        try {
            question_creator::add_to_quiz($created, $kind, self::question_fields($config, $answers, $kind));
        } catch (\moodle_exception $e) {
            debugging('tool_wizards could not add the first question: ' . $e->getMessage(), DEBUG_DEVELOPER);
            \tool_wizards\local\prompt::mark_question_failed();
        }
    }

    /**
     * What it would do.
     *
     * @param array $config the action object
     * @param array $answers question key => answer
     * @return string
     */
    public static function describe(array $config, array $answers): string {
        $kind = (string) ($answers[$config['kind']] ?? '');
        return $kind === '' ? '' : get_string('preview_firstquestion', 'tool_wizards', $kind);
    }

    /**
     * The question type form's fields for the answers.
     *
     * @param array $config the action object
     * @param array $answers question key => answer
     * @param string $kind multichoice or truefalse
     * @return array
     */
    public static function question_fields(array $config, array $answers, string $kind): array {
        $text = trim((string) $answers[$config['text']]);
        $fields = [
            'name' => shorten_text($text, 60),
            'questiontext' => ['text' => '<p>' . nl2br(s($text), false) . '</p>', 'format' => FORMAT_HTML],
        ];
        if ($kind === 'truefalse') {
            $fields['correctanswer'] = (string) (int) !empty($answers[$config['truefalse']]);
            return $fields;
        }
        $slot = 0;
        $fields['answer'] = [];
        $fields['fraction'] = [];
        foreach ($config['choices'] as $i => $key) {
            $choice = trim((string) ($answers[$key] ?? ''));
            if ($choice === '') {
                continue;
            }
            $fields['answer'][$slot] = ['text' => s($choice), 'format' => FORMAT_HTML];
            $fields['fraction'][$slot] = (int) ($answers[$config['correct']] ?? 0) === $i + 1 ? '1.0' : '0.0';
            $slot++;
        }
        return $fields;
    }
}
