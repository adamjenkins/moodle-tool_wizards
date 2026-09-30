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

/**
 * Uninstall steps for tool_wizards.
 *
 * Tables and plugin config are removed by core; user preferences are not.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove this plugin's user preferences.
 *
 * @return bool
 */
function xmldb_tool_wizards_uninstall() {
    global $DB;
    $DB->delete_records_select(
        'user_preferences',
        $DB->sql_like('name', ':prefix'),
        ['prefix' => $DB->sql_like_escape('tool_wizards_') . '%']
    );
    return true;
}
