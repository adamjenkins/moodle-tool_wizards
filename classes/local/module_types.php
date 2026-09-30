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
 * The kinds of first content the wizards offer, and which of them a user may add.
 *
 * Whether a kind is offered is decided only by core: the module must be enabled
 * and the user must be allowed to add it (course_allowed_module(), which checks
 * mod/<name>:addinstance). Anything that restricts that capability, such as
 * tool_teacherscaffold's tier roles, is therefore respected without being named.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_types {
    /**
     * Every kind, in the order offered: type => [module name, icon module].
     *
     * @var array
     */
    const TYPES = [
        'file' => ['modname' => 'resource'],
        'slides' => ['modname' => 'resource'],
        'picture' => ['modname' => 'label'],
        'page' => ['modname' => 'page'],
        'forum' => ['modname' => 'forum'],
        'glossary' => ['modname' => 'glossary'],
        'quiz' => ['modname' => 'quiz'],
    ];

    /**
     * All type names.
     *
     * @return string[]
     */
    public static function all(): array {
        return array_keys(self::TYPES);
    }

    /**
     * The module a type creates.
     *
     * @param string $type the type
     * @return string module name
     * @throws \coding_exception for an unknown type
     */
    public static function modname(string $type): string {
        if (!isset(self::TYPES[$type])) {
            throw new \coding_exception('Unknown wizard content type: ' . $type);
        }
        return self::TYPES[$type]['modname'];
    }

    /**
     * The dynamic form class of a type's mini-wizard.
     *
     * @param string $type the type
     * @return string class name
     */
    public static function formclass(string $type): string {
        self::modname($type);
        return '\\tool_wizards\\form\\add_' . $type;
    }

    /**
     * Whether a user may add this type in a course.
     *
     * @param \stdClass $course the course
     * @param string $type the type
     * @param \stdClass|null $user the user, default the current one
     * @return bool
     */
    public static function is_available(\stdClass $course, string $type, ?\stdClass $user = null): bool {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        if (!isset(self::TYPES[$type])) {
            return false;
        }
        $modname = self::TYPES[$type]['modname'];
        if (!\core_component::get_component_directory('mod_' . $modname) || !class_exists(self::formclass($type))) {
            return false;
        }
        $context = \core\context\course::instance($course->id);
        return has_capability('moodle/course:manageactivities', $context, $user)
            && course_allowed_module($course, $modname, $user);
    }

    /**
     * The types a user may add in a course, in the order offered.
     *
     * @param \stdClass $course the course
     * @param \stdClass|null $user the user, default the current one
     * @return string[]
     */
    public static function available(\stdClass $course, ?\stdClass $user = null): array {
        return array_values(array_filter(self::all(), fn($type) => self::is_available($course, $type, $user)));
    }

    /**
     * The mini-wizard type that best introduces a module, for "try it now" links.
     *
     * @param string $modname a module name
     * @return string|null the type, or null when no mini-wizard adds that module
     */
    public static function type_for_module(string $modname): ?string {
        foreach (self::TYPES as $type => $info) {
            if ($info['modname'] === $modname) {
                return $type;
            }
        }
        return null;
    }
}
