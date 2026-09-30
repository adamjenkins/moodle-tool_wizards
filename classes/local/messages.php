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
 * Messages queued for a user and shown on their next course page view.
 *
 * Today there is one kind: "You've unlocked Quiz, Choice and Feedback", queued when
 * Teacher scaffold reports that a teacher reached a new stage. Each message is shown
 * once, at the top of the next course page the teacher opens.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messages {
    /** @var string Newly unlocked activities. */
    const TYPE_UNLOCK = 'unlock';

    /**
     * Keep only real, installed module names, in their order, without repeats.
     *
     * @param mixed $modules the event's list of module short names
     * @return string[]
     */
    public static function clean_modules($modules): array {
        if (!is_array($modules)) {
            return [];
        }
        $installed = \core_component::get_plugin_list('mod');
        $clean = [];
        foreach ($modules as $modname) {
            if (is_string($modname) && isset($installed[$modname]) && !in_array($modname, $clean, true)) {
                $clean[] = $modname;
            }
        }
        return $clean;
    }

    /**
     * Queue a message for a user.
     *
     * @param int $userid the user
     * @param string $type the kind of message
     * @param array $payload its details
     */
    public static function queue(int $userid, string $type, array $payload): void {
        global $DB;
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return;
        }
        $DB->insert_record('tool_wizards_message', (object) [
            'userid' => $userid,
            'type' => $type,
            'payload' => json_encode($payload),
            'timecreated' => time(),
            'timeshown' => null,
        ]);
    }

    /**
     * The HTML of the next queued message for the current user, marking it shown.
     *
     * @param \stdClass $course the course being viewed
     * @param \renderer_base $renderer the page renderer
     * @return string
     */
    public static function render_for_page(\stdClass $course, \renderer_base $renderer): string {
        global $DB, $USER, $PAGE;
        if (!isloggedin() || isguestuser()) {
            return '';
        }
        $conditions = ['userid' => $USER->id, 'timeshown' => null];
        $rows = $DB->get_records('tool_wizards_message', $conditions, 'timecreated, id', '*', 0, 1);
        $row = reset($rows);
        if (!$row) {
            return '';
        }
        $DB->set_field('tool_wizards_message', 'timeshown', time(), ['id' => $row->id]);

        $payload = json_decode((string) $row->payload, true);
        if ($row->type !== self::TYPE_UNLOCK || !is_array($payload)) {
            return '';
        }
        $modules = self::clean_modules($payload['modules'] ?? []);
        if (!$modules) {
            return '';
        }
        $message = new \tool_wizards\output\unlock_message($course, $modules, !prompt::suggestions_hidden());
        $PAGE->requires->js_call_amd('tool_wizards/first_content', 'init', ['[data-region="tool_wizards-unlock"]']);
        return $renderer->render($message);
    }
}
