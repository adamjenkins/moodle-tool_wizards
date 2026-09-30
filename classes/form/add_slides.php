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

namespace tool_wizards\form;

/**
 * Mini-wizard: add slides, uploaded as a file (mod_resource).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_slides extends add_file {
    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'slides';
    }

    /**
     * Presentation files, and PDF for slides saved that way.
     *
     * @return array
     */
    protected static function accepted_types() {
        return ['.pdf', '.pptx', '.ppt', '.odp', '.key'];
    }

    /**
     * The intro string key.
     *
     * @return string
     */
    protected static function intro_string(): string {
        return 'add_slides_intro';
    }
}
