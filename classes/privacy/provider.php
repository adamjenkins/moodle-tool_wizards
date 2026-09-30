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
 * Privacy subsystem implementation for tool_wizards.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wizards\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for tool_wizards.
 *
 * The plugin keeps three kinds of personal data, all belonging to one user:
 * the "don't show me wizard suggestions" preference, the courses where the user
 * hid the first-content suggestions, and messages queued for the user (for example
 * "you've unlocked Quiz"). Both tables are stored against the user's own context.
 *
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection the collection to add to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_wizards_dismissed', [
            'userid' => 'privacy:metadata:tool_wizards_dismissed:userid',
            'courseid' => 'privacy:metadata:tool_wizards_dismissed:courseid',
            'timecreated' => 'privacy:metadata:tool_wizards_dismissed:timecreated',
        ], 'privacy:metadata:tool_wizards_dismissed');

        $collection->add_database_table('tool_wizards_message', [
            'userid' => 'privacy:metadata:tool_wizards_message:userid',
            'type' => 'privacy:metadata:tool_wizards_message:type',
            'payload' => 'privacy:metadata:tool_wizards_message:payload',
            'timecreated' => 'privacy:metadata:tool_wizards_message:timecreated',
            'timeshown' => 'privacy:metadata:tool_wizards_message:timeshown',
        ], 'privacy:metadata:tool_wizards_message');

        $collection->add_user_preference('tool_wizards_hidesuggestions', 'privacy:metadata:preference:hidesuggestions');

        return $collection;
    }

    /**
     * Get the contexts holding data for a user: their own user context, if any row exists.
     *
     * @param int $userid the user
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::user_has_rows($userid)) {
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    /**
     * Get the users who have data in a context.
     *
     * @param userlist $userlist the userlist to fill
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \core\context\user) {
            return;
        }
        if (self::user_has_rows((int) $context->instanceid)) {
            $userlist->add_user((int) $context->instanceid);
        }
    }

    /**
     * Export the user's data.
     *
     * @param approved_contextlist $contextlist the approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \core\context\user || (int) $context->instanceid !== $userid) {
                continue;
            }

            $dismissed = [];
            foreach ($DB->get_records('tool_wizards_dismissed', ['userid' => $userid], 'id') as $row) {
                $dismissed[] = (object) [
                    'courseid' => $row->courseid,
                    'timecreated' => transform::datetime($row->timecreated),
                ];
            }
            if ($dismissed) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'tool_wizards'), get_string('privacy:path:dismissed', 'tool_wizards')],
                    (object) ['courses' => $dismissed]
                );
            }

            $messages = [];
            foreach ($DB->get_records('tool_wizards_message', ['userid' => $userid], 'id') as $row) {
                $messages[] = (object) [
                    'type' => $row->type,
                    'payload' => $row->payload,
                    'timecreated' => transform::datetime($row->timecreated),
                    'timeshown' => $row->timeshown ? transform::datetime($row->timeshown) : null,
                ];
            }
            if ($messages) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'tool_wizards'), get_string('privacy:path:messages', 'tool_wizards')],
                    (object) ['messages' => $messages]
                );
            }
        }
    }

    /**
     * Export the user's preferences.
     *
     * @param int $userid the user
     */
    public static function export_user_preferences(int $userid) {
        $value = get_user_preferences('tool_wizards_hidesuggestions', null, $userid);
        if ($value !== null) {
            $description = $value ? get_string('privacy:preference:hidesuggestions:yes', 'tool_wizards')
                : get_string('privacy:preference:hidesuggestions:no', 'tool_wizards');
            writer::export_user_preference('tool_wizards', 'tool_wizards_hidesuggestions', (string) $value, $description);
        }
    }

    /**
     * Delete all data in a context, for every user.
     *
     * @param \context $context the context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context instanceof \core\context\user) {
            self::delete_user_rows((int) $context->instanceid);
        }
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \core\context\user && (int) $context->instanceid === $userid) {
                self::delete_user_rows($userid);
            }
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist the approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \core\context\user) {
            return;
        }
        // Data in a user context belongs to that user only.
        if (in_array((int) $context->instanceid, array_map('intval', $userlist->get_userids()), true)) {
            self::delete_user_rows((int) $context->instanceid);
        }
    }

    /**
     * Whether a user has any row in this plugin's tables.
     *
     * @param int $userid the user
     * @return bool
     */
    protected static function user_has_rows(int $userid): bool {
        global $DB;
        return $DB->record_exists('tool_wizards_dismissed', ['userid' => $userid])
            || $DB->record_exists('tool_wizards_message', ['userid' => $userid]);
    }

    /**
     * Delete every row belonging to a user.
     *
     * @param int $userid the user
     */
    protected static function delete_user_rows(int $userid): void {
        global $DB;
        $DB->delete_records('tool_wizards_dismissed', ['userid' => $userid]);
        $DB->delete_records('tool_wizards_message', ['userid' => $userid]);
    }
}
