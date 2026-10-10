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
    /** @var array The plugin's own content handlers: name => class. */
    const BUILTIN_CONTENTS = [
        'quiz_question' => content\quiz_question::class,
        'qbank_question' => content\qbank_question::class,
        'qbank_category' => content\qbank_category::class,
        'lesson_page' => content\lesson_page::class,
        'book_chapter' => content\book_chapter::class,
        'glossary_entry' => content\glossary_entry::class,
        'data_field' => content\data_field::class,
        'feedback_item' => content\feedback_item::class,
        'choice_option' => content\choice_option::class,
        'workshop_form' => content\workshop_form::class,
        'workshop_allocation' => content\workshop_allocation::class,
        'workshop_example' => content\workshop_example::class,
        'workshop_phase' => content\workshop_phase::class,
    ];

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
            foreach (self::BUILTIN_CONTENTS as $name => $class) {
                $hook->add_content('tool_wizards/' . $name, $class);
            }
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
     * The content handlers.
     *
     * @return array name => class
     */
    public static function contents(): array {
        return self::hook()->get_contents();
    }

    /**
     * The content handler a wizard definition targets, if it is an in-activity wizard.
     *
     * @param array $doc the definition
     * @return string|null the handler class
     */
    public static function content_for(array $doc): ?string {
        if (($doc['target']['type'] ?? '') !== 'content') {
            return null;
        }
        return self::contents()[$doc['target']['content'] ?? ''] ?? null;
    }

    /**
     * Forget the collected extensions (for tests).
     */
    public static function reset(): void {
        self::$hook = null;
    }
}
