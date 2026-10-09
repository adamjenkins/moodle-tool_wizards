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

namespace tool_wizards\event;

/**
 * A wizard was deleted.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard_deleted extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'tool_wizards_wizard';
    }

    /**
     * The event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_wizard_deleted', 'tool_wizards');
    }

    /**
     * The description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted the wizard with id '$this->objectid' (key '"
            . s($this->other['key'] ?? '') . "').";
    }

    /**
     * The objectid mapping for restore: wizards are site configuration, not restored.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'tool_wizards_wizard', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * The other mapping.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
