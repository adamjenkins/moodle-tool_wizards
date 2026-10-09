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

use core_course\hook\after_form_definition;
use core_course\hook\after_form_definition_after_data;

/**
 * Carries the wizard's answers over to the standard course form ("Show all settings").
 *
 * course/edit.php accepts no field values in its URL, so the answers wait in the
 * session and are applied as defaults through core's course form hooks. They are
 * used once, only for a new course in the same category, and only if recent.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prefill {
    /** @var int How long the answers wait, in seconds. */
    const LIFETIME = HOURSECS;

    /** @var string[] Fields not set in after_form_definition: the category is the page's, sections the format's. */
    const LATER = ['category', 'numsections', 'id'];

    /**
     * Keep the answers for the standard form.
     *
     * @param \stdClass $answers normalised wizard answers
     * @param int $categoryid the category the standard form will open in
     */
    public static function stash(\stdClass $answers, int $categoryid): void {
        global $SESSION;
        $SESSION->tool_wizards_prefill = (object) [
            'categoryid' => $categoryid,
            'time' => time(),
            'answers' => $answers,
        ];
    }

    /**
     * The waiting answers for a new course in this category, or null.
     *
     * @param \course_edit_form $form the form being built
     * @return \stdClass|null
     */
    protected static function for_form(\course_edit_form $form): ?\stdClass {
        global $SESSION;
        if (empty($SESSION->tool_wizards_prefill) || $form instanceof course_defaults_form) {
            return null;
        }
        $stash = $SESSION->tool_wizards_prefill;
        $course = $form->get_course();
        if (!empty($course->id) || time() - $stash->time > self::LIFETIME) {
            return null;
        }
        $context = $form->get_context();
        if (!$context instanceof \core\context\coursecat || (int) $context->instanceid !== (int) $stash->categoryid) {
            return null;
        }
        return $stash->answers;
    }

    /**
     * Set the wizard's answers as the form's defaults (hook callback).
     *
     * This runs before course/edit_form.php calls set_data(), which for a new course
     * carries no values for these fields, so the defaults stay.
     *
     * @param after_form_definition $hook the hook
     */
    public static function apply(after_form_definition $hook): void {
        $answers = self::for_form($hook->formwrapper);
        if (!$answers) {
            return;
        }
        foreach (get_object_vars($answers) as $field => $value) {
            if (!in_array($field, self::LATER, true) && $value !== '' && $hook->mform->elementExists($field)) {
                $hook->mform->setDefault($field, $value);
            }
        }
    }

    /**
     * Set the number of sections, once the chosen format has added its fields, and
     * forget the answers (hook callback).
     *
     * The number of sections belongs to the format, and core_courseformat\base sets
     * its default only when none is set yet, which is why it waits until here.
     *
     * @param after_form_definition_after_data $hook the hook
     */
    public static function apply_format_options(after_form_definition_after_data $hook): void {
        global $SESSION;
        $answers = self::for_form($hook->formwrapper);
        if (!$answers) {
            return;
        }
        if (isset($answers->numsections) && $hook->mform->elementExists('numsections')) {
            $hook->mform->setDefault('numsections', $answers->numsections);
        }
        unset($SESSION->tool_wizards_prefill);
    }
}
