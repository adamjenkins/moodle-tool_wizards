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

/**
 * The question categories of one question bank, as a tree, for the question bank wizards.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_categories {
    /**
     * The bank's categories in tree order, each with its path ("Chapter 7 › Vocabulary").
     *
     * @param \context $context the bank's context (a question bank's or a quiz's module context)
     * @param bool $withtop whether to include the bank's top category (where new top-level categories go;
     *                      questions cannot go there)
     * @return array of ['id' => int, 'name' => string (the path, plain text), 'depth' => int]
     */
    public static function tree(\context $context, bool $withtop = false): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $top = question_get_top_category($context->id, true);
        $children = [];
        foreach ($DB->get_records('question_categories', ['contextid' => $context->id], 'sortorder, id') as $category) {
            $children[(int) $category->parent][] = $category;
        }
        $out = [];
        if ($withtop) {
            $out[] = ['id' => (int) $top->id, 'name' => get_string('qbankcategory_top', 'tool_wizards'), 'depth' => 0];
        }
        $walk = function (int $parentid, array $path) use (&$walk, &$out, $children, $context) {
            foreach ($children[$parentid] ?? [] as $category) {
                $name = format_string($category->name, true, ['context' => $context, 'escape' => false]);
                $out[] = ['id' => (int) $category->id, 'name' => implode(' › ', array_merge($path, [$name])),
                    'depth' => count($path) + 1];
                $walk((int) $category->id, array_merge($path, [$name]));
            }
        };
        $walk((int) $top->id, []);
        return $out;
    }

    /**
     * Whether a category belongs to this bank (and, unless the top one is allowed, is not its top category).
     *
     * @param \context $context the bank's context
     * @param int $categoryid the category
     * @param bool $withtop whether the top category counts
     * @return bool
     */
    public static function belongs(\context $context, int $categoryid, bool $withtop = false): bool {
        return in_array($categoryid, array_column(self::tree($context, $withtop), 'id'), true);
    }
}
