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
 * Gives out a workshop's reviews at random, with Moodle's random allocator.
 *
 * The settings are the random allocator's own (mod/workshop/allocation/random/settings_form.php,
 * workshop_random_allocator_setting); they run through workshop_random_allocator::execute() as
 * mod/workshop/allocation.php (via init()) and the scheduled allocator
 * (mod/workshop/allocation/scheduled/lib.php execute()) do.
 *
 * Fields:
 * - numofreviews: how many reviews (1-100);
 * - numper: 1 = each piece of work gets that many reviews, 2 = each student does that many;
 * - assesswosubmission: students who handed in nothing review too (1/0);
 * - addselfassessment: students also review their own work (1/0; only when self-assessment is on);
 * - excludesamegroup: no reviews within a group (1/0; visible groups only, as core's form);
 * - removecurrent: remove the current allocations that have no mark yet (1/0).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshop_allocation extends content {
    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'workshop';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'numofreviews' => 'how many reviews, 1-100',
            'numper' => '1 = per piece of work, 2 = per student reviewing',
            'assesswosubmission' => '1 = students who handed in nothing review too',
            'addselfassessment' => '1 = students also review their own work (self-assessment on)',
            'excludesamegroup' => '1 = no reviews within the same group (visible groups)',
            'removecurrent' => '1 = remove the current allocations that have no mark yet',
        ];
    }

    /**
     * Page types: the allocation page and the workshop page.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-workshop-allocation', 'mod-workshop-view'];
    }

    /**
     * Allocating needs the workshop's own capability (as allocation.php).
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/workshop:allocate'];
    }

    /**
     * Run it once; running it again is a new decision.
     *
     * @return bool
     */
    public static function repeatable(): bool {
        return false;
    }

    /**
     * The workshop API object.
     *
     * @param \cm_info $cm the activity
     * @return \workshop
     */
    protected static function workshop(\cm_info $cm): \workshop {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/workshop/locallib.php');
        $record = $DB->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST);
        return new \workshop($record, $cm, $cm->get_course());
    }

    /**
     * Available until reviewing is over (setup, submission and assessment phases).
     *
     * @param \cm_info $cm the activity
     * @return bool
     */
    public static function is_available(\cm_info $cm): bool {
        return (int) self::workshop($cm)->phase < \workshop::PHASE_EVALUATION;
    }

    /**
     * What the allocator offers here: self-assessment (when the workshop has it on) and keeping
     * groups apart (visible groups only, as the random allocator's settings form).
     *
     * @param \cm_info $cm the activity
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        $workshop = self::workshop($cm);
        $offered = ['selfassessment' => [empty($workshop->useselfassessment) ? '0' : '1']];
        $groupmode = groups_get_activity_groupmode($workshop->cm, $workshop->course);
        $offered['visiblegroups'] = [$groupmode == VISIBLEGROUPS ? '1' : '0'];
        return $offered;
    }

    /**
     * The number of real (not example) allocations.
     *
     * @param int $workshopid the workshop
     * @return int
     */
    protected static function count_allocations(int $workshopid): int {
        global $DB;
        return $DB->count_records_sql('SELECT COUNT(a.id)
                                         FROM {workshop_assessments} a
                                         JOIN {workshop_submissions} s ON s.id = a.submissionid
                                        WHERE s.workshopid = ? AND s.example = 0', [$workshopid]);
    }

    /**
     * Check the settings; refuse when there is no work to review yet.
     *
     * @param \cm_info $cm the activity
     * @param array $fields the fields
     * @return array field => message
     */
    public static function check(\cm_info $cm, array $fields): array {
        global $DB;
        $errors = [];
        $num = trim((string) ($fields['numofreviews'] ?? ''));
        if (!is_numeric($num) || (float) $num != (int) $num || (int) $num < 1 || (int) $num > 100) {
            $errors['numofreviews'] = get_string('error_range', 'tool_wizards', ['min' => 1, 'max' => 100]);
        } else if (!$DB->record_exists('workshop_submissions', ['workshopid' => $cm->instance, 'example' => 0])) {
            $errors['numofreviews'] = get_string('workshopallocation_error_nowork', 'tool_wizards');
        }
        if (!in_array((int) ($fields['numper'] ?? 1), [1, 2], true)) {
            $errors['numper'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Run the random allocator with these settings.
     *
     * @param \cm_info $cm the activity
     * @param array $fields the fields
     * @return string e.g. "Reviews given out: 12 (before: 0)"
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $CFG;
        $workshop = self::workshop($cm);
        require_once($CFG->dirroot . '/mod/workshop/allocation/lib.php');
        $allocator = $workshop->allocator_instance('random');
        $offered = self::offered($cm);
        $settings = \workshop_random_allocator_setting::instance_from_object((object) [
            'numofreviews' => (int) $fields['numofreviews'],
            'numper' => (int) ($fields['numper'] ?? \workshop_random_allocator_setting::NUMPER_SUBMISSION),
            'excludesamegroup' => $offered['visiblegroups'] === ['1'] && !empty($fields['excludesamegroup']),
            'removecurrent' => !empty($fields['removecurrent']),
            'assesswosubmission' => !empty($fields['assesswosubmission']),
            'addselfassessment' => $offered['selfassessment'] === ['1'] && !empty($fields['addselfassessment']),
        ]);
        $before = self::count_allocations($workshop->id);
        $result = new \workshop_allocation_result($allocator);
        $allocator->execute($settings, $result);
        $after = self::count_allocations($workshop->id);
        return get_string('workshopallocation_added', 'tool_wizards', ['after' => $after, 'before' => $before]);
    }

    /**
     * Where the teacher sees the result: the allocation page.
     *
     * @param \cm_info $cm the activity
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/workshop/allocation.php', ['cmid' => $cm->id, 'method' => 'manual']);
    }
}
