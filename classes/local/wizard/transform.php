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
 * A transform: code that turns several answers into form fields.
 *
 * A definition uses one in a question's "sets": {"transform": "<name>", "from": {name: question key}}.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class transform {
    /**
     * The names the transform expects in "from".
     *
     * @return string[]
     */
    abstract public static function inputs(): array;

    /**
     * The form fields for the answers.
     *
     * @param array $inputs name => answer (from "from")
     * @return array form field => value
     */
    abstract public static function apply(array $inputs): array;
}
