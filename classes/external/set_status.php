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

namespace tool_wizards\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_wizards\local\wizard\repository;

/**
 * Switch wizards on or off from the wizard list, one or a whole group at a time. Drafts are left
 * as they are: a draft is enabled deliberately, after trying it.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ids' => new external_multiple_structure(new external_value(PARAM_INT, 'A wizard')),
            'enabled' => new external_value(PARAM_BOOL, 'Switch them on (true) or off (false)'),
        ]);
    }

    /**
     * Switch the wizards.
     *
     * @param int[] $ids the wizards
     * @param bool $enabled on or off
     * @return array [['id' => int, 'status' => int]] every wizard asked about, as it is now
     */
    public static function execute(array $ids, bool $enabled): array {
        ['ids' => $ids, 'enabled' => $enabled] = self::validate_parameters(
            self::execute_parameters(),
            ['ids' => $ids, 'enabled' => $enabled]
        );
        $context = \core\context\system::instance();
        self::validate_context($context);
        require_capability('tool/wizards:managewizards', $context);
        $out = [];
        foreach (array_unique($ids) as $id) {
            $record = repository::get($id);
            if (!$record) {
                continue;
            }
            $status = (int) $record->status;
            $wanted = $enabled ? repository::STATUS_ENABLED : repository::STATUS_DISABLED;
            if ($status !== repository::STATUS_DRAFT && $status !== $wanted) {
                repository::set_status($id, $wanted);
                $status = $wanted;
            }
            $out[] = ['id' => $id, 'status' => $status];
        }
        return $out;
    }

    /**
     * Returns.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The wizard'),
            'status' => new external_value(PARAM_INT, 'Its status now: 0 disabled, 1 enabled, 2 draft'),
        ]));
    }
}
