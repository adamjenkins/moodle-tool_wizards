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

/**
 * An action: code that runs after a wizard has created its course or activity.
 *
 * A definition lists actions as {"action": "<name>", "when": {…}, …config}. The config
 * names the questions the action reads.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class action {
    /**
     * Check the action's config in a definition.
     *
     * @param array $config the action object from the definition
     * @param string[] $questionkeys the definition's question keys
     * @return string[] problems
     */
    abstract public static function check_config(array $config, array $questionkeys): array;

    /**
     * Check the answers before anything is created.
     *
     * @param array $config the action object
     * @param array $answers question key => answer
     * @param \context $context the course context
     * @return array question key => error message
     */
    public static function validate(array $config, array $answers, \context $context): array {
        return [];
    }

    /**
     * Do it.
     *
     * @param array $config the action object
     * @param array $answers question key => answer
     * @param \cm_info|\stdClass $created the new course module, or the new course
     */
    abstract public static function run(array $config, array $answers, $created): void;

    /**
     * What the action would do, for "Try it" (preview).
     *
     * @param array $config the action object
     * @param array $answers question key => answer
     * @return string a short description, or '' when it would do nothing
     */
    public static function describe(array $config, array $answers): string {
        return '';
    }
}
