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
 * Explains which stage (Moodle: "phase") a workshop is in and moves it to another, with
 * workshop::switch_phase() as mod/workshop/switchphase.php does. Like that page, it does nothing
 * else: grading evaluation is not run, and closing the workshop pushes the grades to the
 * gradebook inside switch_phase() itself.
 *
 * Fields:
 * - phase: the stage to move to (10 setup, 20 submission, 30 assessment, 40 grading evaluation,
 *   50 closed; the workshop::PHASE_ constants).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshop_phase extends content {
    /** @var int[] The phases, in order (workshop::PHASE_SETUP .. workshop::PHASE_CLOSED). */
    const PHASES = [10, 20, 30, 40, 50];

    /** @var string[] Phase => mod_workshop string with its name. */
    const NAMES = [10 => 'phasesetup', 20 => 'phasesubmission', 30 => 'phaseassessment', 40 => 'phaseevaluation',
        50 => 'phaseclosed'];

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
            'phase' => 'the stage to move to: 10 setup, 20 submission, 30 assessment, 40 grading evaluation, 50 closed',
        ];
    }

    /**
     * Page types: the workshop page (its phase planner).
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-workshop-view'];
    }

    /**
     * Switching phases needs the workshop's own capability (as switchphase.php).
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/workshop:switchphase'];
    }

    /**
     * One switch at a time.
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
     * The current phase, for {"offered": {"field": "phase", "value": 20}}.
     *
     * @param \cm_info $cm the activity
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        return ['phase' => [(string) (int) self::workshop($cm)->phase]];
    }

    /**
     * The new phase must be a phase, and not the current one.
     *
     * @param \cm_info $cm the activity
     * @param array $fields the fields
     * @return array field => message
     */
    public static function check(\cm_info $cm, array $fields): array {
        $phase = (int) ($fields['phase'] ?? 0);
        if (!in_array($phase, self::PHASES, true)) {
            return ['phase' => get_string('required')];
        }
        if ($phase === (int) self::workshop($cm)->phase) {
            return ['phase' => get_string('workshopphase_error_same', 'tool_wizards')];
        }
        return [];
    }

    /**
     * Switch the phase.
     *
     * @param \cm_info $cm the activity
     * @param array $fields the fields
     * @return string e.g. "Stage now: Assessment phase"
     */
    public static function save(\cm_info $cm, array $fields): string {
        $phase = (int) $fields['phase'];
        $workshop = self::workshop($cm);
        if (!$workshop->switch_phase($phase)) {
            // Core's switchphase.php names the string "errorswitchingphase", which mod_workshop does not define.
            throw new \moodle_exception('workshopphase_error_switch', 'tool_wizards', $workshop->view_url());
        }
        return get_string('workshopphase_added', 'tool_wizards', get_string(self::NAMES[$phase], 'workshop'));
    }

    /**
     * Where the teacher sees the result: the workshop page.
     *
     * @param \cm_info $cm the activity
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/workshop/view.php', ['id' => $cm->id]);
    }
}
