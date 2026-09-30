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

use stdClass;

/**
 * Adds an activity or resource to a course from a mini-wizard's few answers, the way
 * course/modedit.php does.
 *
 * The module's own settings form is built exactly as modedit.php builds it for a new
 * activity and submitted as a teacher would who filled in only the mini-wizard's
 * questions ({@see form_submission}). So every other setting is the form's own
 * default: the module's admin defaults, the course's completion defaults, and whatever
 * other plugins add to every activity form. The form's own validation and
 * post-processing run, and the module is created with add_moduleinfo() and that form,
 * as modedit.php does, so events, completion, the gradebook and files behave as usual.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_creator {
    /**
     * The section new content goes into: the first one after the general section, if the course has one.
     *
     * @param stdClass $course the course
     * @return int section number
     */
    public static function default_section(stdClass $course): int {
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_listed_section_info_all() as $section) {
            if ($section->section > 0) {
                return (int) $section->section;
            }
        }
        return 0;
    }

    /**
     * Create the module.
     *
     * @param stdClass $course the course
     * @param string $type the mini-wizard type (see module_types)
     * @param array $answers submitted field => value for the module's own form, for example
     *                       'name' => 'Week 1', 'introeditor' => ['text' => '<p>Hi</p>', 'format' => 1]
     * @param int|null $sectionnum the section, default {@see default_section()}
     * @return \cm_info the new course module
     * @throws \moodle_exception when the user may not add it, or the module's form refuses the answers
     */
    public static function create(stdClass $course, string $type, array $answers, ?int $sectionnum = null): \cm_info {
        [$mform, $fromform, $errors] = self::submit($course, $type, $answers, $sectionnum);
        if (!$fromform) {
            throw new \moodle_exception('error_moduleform', 'tool_wizards', '', null, json_encode($errors));
        }

        // As course/modedit.php does: a large regrade is queued rather than run in this request.
        $fromform->frontend = true;
        $result = add_moduleinfo($fromform, $course, $mform);
        return get_fast_modinfo($course->id)->get_cm($result->coursemodule);
    }

    /**
     * What the module's own form says about the answers, without creating anything.
     *
     * @param stdClass $course the course
     * @param string $type the mini-wizard type (see module_types)
     * @param array $answers field => value for the module's own form, as for {@see create()}
     * @return array field => error message; empty when the form accepts them
     */
    public static function check(stdClass $course, string $type, array $answers): array {
        [, , $errors] = self::submit($course, $type, $answers);
        return $errors;
    }

    /**
     * Submit the answers to the module's own form, built as course/modedit.php builds it.
     *
     * @param stdClass $course the course
     * @param string $type the mini-wizard type
     * @param array $answers field => value for the module's own form
     * @param int|null $sectionnum the section, default {@see default_section()}
     * @return array [\moodleform $mform, \stdClass|null $fromform, array $errors]
     */
    protected static function submit(stdClass $course, string $type, array $answers, ?int $sectionnum = null): array {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/formslib.php');

        $modname = module_types::modname($type);
        // Ask core before anything else: can_add_moduleinfo() would create a missing section
        // before finding out the module is not allowed.
        if (!module_types::is_available($course, $type)) {
            throw new \moodle_exception('error_notavailable', 'tool_wizards');
        }
        $sectionnum ??= self::default_section($course);
        if (!get_fast_modinfo($course)->get_section_info($sectionnum)) {
            throw new \moodle_exception('error_nosection', 'tool_wizards');
        }

        // What course/modedit.php sets up before building the form: activity forms read
        // $COURSE (set by require_login() there) and the page context.
        if ((int) $PAGE->course->id !== (int) $course->id) {
            $PAGE->set_course($course);
        }
        $PAGE->set_context(\core\context\course::instance($course->id));
        if (!$PAGE->has_set_url()) {
            $PAGE->set_url(new \moodle_url('/course/modedit.php', ['add' => $modname, 'course' => $course->id]));
        }

        // The same preparation as course/modedit.php for "add"; this also checks
        // moodle/course:manageactivities and whether the module may be added.
        [, , $cw, $cm, $data] = prepare_new_moduleinfo_data($course, $modname, $sectionnum);
        $data->return = 0;
        $data->add = $modname;
        require_once($CFG->dirroot . '/mod/' . $modname . '/mod_form.php');
        $classname = 'mod_' . $modname . '_mod_form';
        $factory = function () use ($classname, $data, $cw, $cm, $course) {
            $form = new $classname(clone $data, $cw->section, $cm, $course);
            $form->set_data(clone $data);
            return $form;
        };

        $values = form_submission::browser_values($factory(), $answers);
        return form_submission::submit($factory, $values);
    }
}
