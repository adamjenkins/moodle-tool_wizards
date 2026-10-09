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

namespace tool_wizards\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;

/**
 * "You've unlocked Quiz, Choice and Feedback. Want to try a quiz now?"
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unlock_message implements renderable, templatable {
    /** @var \stdClass the course being viewed */
    protected \stdClass $course;

    /** @var string[] the unlocked module names */
    protected array $modules;

    /** @var bool whether to offer a mini-wizard ("Try it now") */
    protected bool $offertry;

    /**
     * Constructor.
     *
     * @param \stdClass $course the course being viewed
     * @param string[] $modules the unlocked module names
     * @param bool $offertry whether to offer a mini-wizard; false when the teacher turned suggestions off
     */
    public function __construct(\stdClass $course, array $modules, bool $offertry) {
        $this->course = $course;
        $this->modules = $modules;
        $this->offertry = $offertry;
    }

    /**
     * Join names into a list, "A, B and C", with separators translators can change.
     *
     * @param string[] $names two or more names
     * @return string
     */
    protected static function join_names(array $names): string {
        $last = array_pop($names);
        return get_string('unlock_list', 'tool_wizards', (object) [
            'first' => implode(get_string('unlock_separator', 'tool_wizards'), $names),
            'last' => $last,
        ]);
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output the renderer
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $names = array_map(fn($modname) => get_string('modulename', $modname), $this->modules);

        // Offer the first wizard, in the admin's order, for an unlocked module that can be added here.
        $try = null;
        if ($this->offertry) {
            foreach (\tool_wizards\local\wizard\repository::for_course($this->course) as $record) {
                if (in_array($record->target, $this->modules, true)) {
                    $doc = \tool_wizards\local\wizard\repository::definition($record);
                    $try = [
                        'type' => $record->wizardkey,
                        'modaltitle' => \tool_wizards\local\wizard\text::get($doc['heading'] ?? $doc['title'] ?? ''),
                        'label' => get_string('unlock_try', 'tool_wizards', get_string('modulename', $record->target)),
                    ];
                    break;
                }
            }
        }

        return [
            'courseid' => (int) $this->course->id,
            'message' => count($names) === 1
                ? get_string('unlock_message_one', 'tool_wizards', $names[0])
                : get_string('unlock_message', 'tool_wizards', self::join_names($names)),
            'try' => $try,
        ];
    }
}
