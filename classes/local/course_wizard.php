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
 * Small helpers for the course wizard page: its steps, and turning its inputs into values.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_wizard {
    /** @var string[] The wizard's steps, in order. Some are skipped depending on the answers. */
    const STEPS = ['name', 'category', 'layout', 'sections', 'visibility', 'startdate', 'review'];

    /** @var array Which step asks for which answer, for returning to the step with an error. */
    const FIELD_STEPS = [
        'fullname' => 'name',
        'shortname' => 'name',
        'category' => 'category',
        'format' => 'layout',
        'numsections' => 'sections',
        'startdate' => 'startdate',
        'enddate' => 'startdate',
        'visible' => 'visibility',
    ];

    /**
     * Parse a date from an HTML date input (YYYY-MM-DD) as midnight in the user's time zone.
     *
     * @param string $value the submitted value
     * @return int|null timestamp, or null when empty or not a real date
     */
    public static function parse_date(string $value): ?int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }
        [, $year, $month, $day] = array_map('intval', $m);
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return make_timestamp($year, $month, $day);
    }

    /**
     * Format a timestamp for an HTML date input, in the user's time zone.
     *
     * @param int $time timestamp
     * @return string YYYY-MM-DD
     */
    public static function format_date(int $time): string {
        return userdate($time, '%Y-%m-%d', 99, false, false);
    }

    /**
     * The step to reopen for an error on a field. Errors on fields the wizard does
     * not ask about are shown on the review step.
     *
     * @param string|null $field the field with the error
     * @return string step name
     */
    public static function step_for_error(?string $field): string {
        return self::FIELD_STEPS[$field] ?? 'review';
    }
}
