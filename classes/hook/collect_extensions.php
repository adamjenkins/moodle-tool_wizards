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

namespace tool_wizards\hook;

/**
 * Lets plugins add building blocks that wizard definitions can use.
 *
 * - An action runs after the wizard has created its course or activity, for things a
 *   definition cannot express as form fields (the default quiz wizard's "add a first
 *   question" is one). Its class extends \tool_wizards\local\wizard\action.
 * - A transform turns several answers into form fields in code (the picture wizard's
 *   "picture, description and caption into a text and media area" is one). Its class
 *   extends \tool_wizards\local\wizard\transform.
 *
 * Names are "<component>/<name>", for example "local_myplugin/send_welcome".
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Add actions and transforms that wizard definitions can use.')]
#[\core\attribute\tags('tool_wizards')]
final class collect_extensions {
    /** @var array name => action class */
    protected array $actions = [];

    /** @var array name => transform class */
    protected array $transforms = [];

    /** @var array name => content handler class */
    protected array $contents = [];

    /**
     * Add an action.
     *
     * @param string $name "<component>/<name>"
     * @param string $classname a subclass of \tool_wizards\local\wizard\action
     */
    public function add_action(string $name, string $classname): void {
        if (self::name_ok($name) && is_subclass_of($classname, \tool_wizards\local\wizard\action::class)) {
            $this->actions[$name] = $classname;
        } else {
            debugging("tool_wizards: ignored action $name ($classname)", DEBUG_DEVELOPER);
        }
    }

    /**
     * Add a transform.
     *
     * @param string $name "<component>/<name>"
     * @param string $classname a subclass of \tool_wizards\local\wizard\transform
     */
    public function add_transform(string $name, string $classname): void {
        if (self::name_ok($name) && is_subclass_of($classname, \tool_wizards\local\wizard\transform::class)) {
            $this->transforms[$name] = $classname;
        } else {
            debugging("tool_wizards: ignored transform $name ($classname)", DEBUG_DEVELOPER);
        }
    }

    /**
     * Add a content handler, for in-activity wizards.
     *
     * @param string $name "<component>/<name>"
     * @param string $classname a subclass of \tool_wizards\local\wizard\content
     */
    public function add_content(string $name, string $classname): void {
        if (self::name_ok($name) && is_subclass_of($classname, \tool_wizards\local\wizard\content::class)) {
            $this->contents[$name] = $classname;
        } else {
            debugging("tool_wizards: ignored content handler $name ($classname)", DEBUG_DEVELOPER);
        }
    }

    /**
     * The content handlers added.
     *
     * @return array name => class
     */
    public function get_contents(): array {
        return $this->contents;
    }

    /**
     * The actions added.
     *
     * @return array name => class
     */
    public function get_actions(): array {
        return $this->actions;
    }

    /**
     * The transforms added.
     *
     * @return array name => class
     */
    public function get_transforms(): array {
        return $this->transforms;
    }

    /**
     * Whether a name is "<component>/<name>".
     *
     * @param string $name the name
     * @return bool
     */
    protected static function name_ok(string $name): bool {
        return (bool) preg_match('/^[a-z][a-z0-9_]*\/[a-z][a-z0-9_]*$/', $name);
    }
}
