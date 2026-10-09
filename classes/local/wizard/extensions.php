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

namespace tool_wizards\local\wizard;

use tool_wizards\hook\collect_extensions;

/**
 * The actions and transforms definitions can use: this plugin's own, plus those other
 * plugins add through \tool_wizards\hook\collect_extensions.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extensions {
    /** @var collect_extensions|null the hook, once dispatched in this request */
    protected static ?collect_extensions $hook = null;

    /**
     * Dispatch the hook once, with this plugin's own extensions added first.
     *
     * @return collect_extensions
     */
    protected static function hook(): collect_extensions {
        if (self::$hook === null) {
            $hook = new collect_extensions();
            $hook->add_action('tool_wizards/quiz_first_question', action\quiz_first_question::class);
            $hook->add_transform('tool_wizards/picture_label', transform\picture_label::class);
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            self::$hook = $hook;
        }
        return self::$hook;
    }

    /**
     * The actions.
     *
     * @return array name => class
     */
    public static function actions(): array {
        return self::hook()->get_actions();
    }

    /**
     * The transforms.
     *
     * @return array name => class
     */
    public static function transforms(): array {
        return self::hook()->get_transforms();
    }

    /**
     * Forget the collected extensions (for tests).
     */
    public static function reset(): void {
        self::$hook = null;
    }
}
