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

use tool_wizards\local\question_categories;

/**
 * Sets up a question bank's categories in one go: one category for each chapter, unit, week or
 * lesson in a range (or for each topic in a list), each with the same subcategories if wanted
 * ("Chapter 7" › "Vocabulary", "Grammar", ...).
 *
 * Categories are added with core's category manager, as the bank's Categories page adds them.
 * A category that is already there with the same name, in the same place, is used rather than
 * added again, so the wizard can be run again to add subcategories or more chapters.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbank_category extends \tool_wizards\local\wizard\content {
    /** @var string[] Ways of dividing the bank that number their categories. */
    const NUMBERED = ['chapter', 'unit', 'week', 'lesson', 'custom'];

    /** @var string[] Common subcategories offered as tick boxes. */
    const COMMON = ['vocabulary', 'grammar', 'reading', 'listening', 'writing', 'speaking'];

    /** @var int Most categories one run may add, subcategories included. */
    const MAX = 200;

    /**
     * The activity.
     *
     * @return string
     */
    public static function modname(): string {
        return 'qbank';
    }

    /**
     * The fields.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'scheme' => 'how the bank is divided: chapter, unit, week, lesson, custom (numbered with your own word) or topics',
            'from' => 'the first number (numbered ways)',
            'to' => 'the last number (numbered ways)',
            'label' => 'the word before each number, for "custom" (e.g. "Part")',
            'topics' => 'one category name per line, for "topics"',
            'common' => 'common subcategories to add in each category: common[vocabulary] = 1, also grammar, reading, '
                . 'listening, writing, speaking',
            'others' => 'more subcategories to add in each category, one per line',
            'parent' => 'the category to put them in (the bank\'s top level by default)',
        ];
    }

    /**
     * The bank's own pages: the question list and the categories page.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['question-edit', 'question-bank-managecategories-category'];
    }

    /**
     * Managing categories is needed.
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['moodle/question:managecategory'];
    }

    /**
     * One run sets up the whole structure.
     *
     * @return bool
     */
    public static function repeatable(): bool {
        return false;
    }

    /**
     * The bank's categories page.
     *
     * @param \cm_info $cm the bank
     * @return \moodle_url
     */
    public static function url(\cm_info $cm): \moodle_url {
        return new \moodle_url('/question/bank/managecategories/category.php', ['cmid' => $cm->id]);
    }

    /**
     * The categories to add: name => subcategory names.
     *
     * @param array $fields the fields
     * @return array name => string[]
     */
    public static function plan(array $fields): array {
        $scheme = (string) ($fields['scheme'] ?? '');
        $names = [];
        if ($scheme === 'topics') {
            $names = self::lines((string) ($fields['topics'] ?? ''));
        } else if (in_array($scheme, self::NUMBERED, true)) {
            $from = (int) ($fields['from'] ?? 0);
            $to = (int) ($fields['to'] ?? 0);
            if ($from >= 0 && $to >= $from && $to - $from < self::MAX) {
                $label = trim((string) ($fields['label'] ?? ''));
                for ($n = $from; $n <= $to; $n++) {
                    $names[] = $scheme === 'custom'
                        ? trim($label . ' ' . $n)
                        : get_string('qbankcategory_name_' . $scheme, 'tool_wizards', $n);
                }
            }
        }
        $subs = [];
        foreach (self::COMMON as $common) {
            if (!empty($fields['common'][$common])) {
                $subs[] = get_string('qbankcategory_common_' . $common, 'tool_wizards');
            }
        }
        $subs = array_values(array_unique(array_merge($subs, self::lines((string) ($fields['others'] ?? '')))));
        return array_fill_keys(array_unique($names), $subs);
    }

    /**
     * Check the fields.
     *
     * @param \cm_info $cm the bank
     * @param array $fields the fields
     * @return array field => message
     */
    public static function check(\cm_info $cm, array $fields): array {
        $errors = [];
        $scheme = (string) ($fields['scheme'] ?? '');
        if (!in_array($scheme, array_merge(self::NUMBERED, ['topics']), true)) {
            return ['scheme' => get_string('error_purpose', 'tool_wizards')];
        }
        if ($scheme === 'topics') {
            if (!self::lines((string) ($fields['topics'] ?? ''))) {
                $errors['topics'] = get_string('qbankcategory_error_topics', 'tool_wizards');
            }
        } else {
            $from = $fields['from'] ?? '';
            $to = $fields['to'] ?? '';
            if (!is_numeric($from) || (int) $from != $from || $from < 0) {
                $errors['from'] = get_string('qbankcategory_error_number', 'tool_wizards');
            }
            if (!is_numeric($to) || (int) $to != $to || $to < 0) {
                $errors['to'] = get_string('qbankcategory_error_number', 'tool_wizards');
            } else if (!isset($errors['from']) && $to < $from) {
                $errors['to'] = get_string('qbankcategory_error_order', 'tool_wizards');
            }
            if ($scheme === 'custom' && trim((string) ($fields['label'] ?? '')) === '') {
                $errors['label'] = get_string('required');
            }
        }
        if ($errors) {
            return $errors;
        }
        $plan = self::plan($fields);
        $total = count($plan) * (1 + count(reset($plan) ?: []));
        if ($total > self::MAX) {
            $errors[$scheme === 'topics' ? 'topics' : 'to'] = get_string('qbankcategory_error_toomany', 'tool_wizards', self::MAX);
        }
        foreach ($plan as $name => $subs) {
            foreach (array_merge([$name], $subs) as $one) {
                if (\core_text::strlen($one) > 255) {
                    $errors[$scheme === 'topics' ? 'topics' : 'others'] = get_string('maximumchars', '', 255);
                }
            }
        }
        $parent = (int) ($fields['parent'] ?? 0);
        if ($parent && !question_categories::belongs($cm->context, $parent, true)) {
            $errors['parent'] = get_string('question_error_category', 'tool_wizards');
        }
        return $errors;
    }

    /**
     * Add the categories.
     *
     * @param \cm_info $cm the bank
     * @param array $fields the fields
     * @return string what was added
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $context = $cm->context;
        $parent = (int) ($fields['parent'] ?? 0) ?: (int) question_get_top_category($context->id, true)->id;
        $manager = new \core_question\category_manager();
        $added = 0;
        $kept = 0;
        $find = function (int $parentid, string $name) use ($DB, $context): ?int {
            foreach ($DB->get_records('question_categories', ['contextid' => $context->id, 'parent' => $parentid]) as $existing) {
                if (\core_text::strtolower(trim($existing->name)) === \core_text::strtolower($name)) {
                    return (int) $existing->id;
                }
            }
            return null;
        };
        $add = function (int $parentid, string $name) use ($manager, $context, $find, &$added, &$kept): int {
            $id = $find($parentid, $name);
            if ($id) {
                $kept++;
                return $id;
            }
            $added++;
            return $manager->add_category($parentid . ',' . $context->id, $name, '', FORMAT_HTML);
        };
        $plan = self::plan($fields);
        foreach ($plan as $name => $subs) {
            $id = $add($parent, $name);
            foreach ($subs as $sub) {
                $add($id, $sub);
            }
        }
        $names = array_keys($plan);
        $a = (object) ['count' => $added, 'first' => reset($names), 'last' => end($names), 'kept' => $kept];
        return get_string($kept ? 'qbankcategory_added_kept' : 'qbankcategory_added', 'tool_wizards', $a);
    }

    /**
     * The non-empty lines of a text, trimmed, without repeats.
     *
     * @param string $text the text
     * @return string[]
     */
    protected static function lines(string $text): array {
        $lines = array_map(fn($line) => clean_param(trim($line), PARAM_TEXT), preg_split('/\R/', $text));
        return array_values(array_unique(array_filter($lines, fn($line) => $line !== '')));
    }
}
