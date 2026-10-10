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
     * Whether a user may add a module to a course: the module is installed and visible,
     * and the user can manage activities there and add this one.
     *
     * @param stdClass $course the course
     * @param string $modname the module
     * @param stdClass|null $user the user, default the current one
     * @return bool
     */
    public static function is_available(stdClass $course, string $modname, ?stdClass $user = null): bool {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        if (
            !preg_match('/^[a-z][a-z0-9_]*$/', $modname) || !\core_component::get_component_directory('mod_' . $modname)
                || !is_readable($CFG->dirroot . '/mod/' . $modname . '/mod_form.php')
        ) {
            return false;
        }
        $context = \core\context\course::instance($course->id);
        return has_capability('moodle/course:manageactivities', $context, $user)
            && course_allowed_module($course, $modname, $user)
            // Since Moodle 4.3 an external tool is always one of the tools set up for the site or course.
            && ($modname !== 'lti' || self::lti_tools($course, $user));
    }

    /**
     * The external tools a user may add to a course: the site's and the course's own preconfigured tools.
     *
     * @param stdClass $course the course
     * @param stdClass|null $user the user, default the current one
     * @return stdClass[] tool types
     */
    public static function lti_tools(stdClass $course, ?stdClass $user = null): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        return array_values(\mod_lti\local\types_helper::get_lti_types_by_course((int) $course->id, (int) ($user ?? $USER)->id));
    }

    /**
     * Create the module.
     *
     * @param stdClass $course the course
     * @param string $modname the module
     * @param array $answers submitted field => value for the module's own form, for example
     *                       'name' => 'Week 1', 'introeditor' => ['text' => '<p>Hi</p>', 'format' => 1]
     * @param int|null $sectionnum the section, default {@see default_section()}
     * @return \cm_info the new course module
     * @throws \moodle_exception when the user may not add it, or the module's form refuses the answers
     */
    public static function create(stdClass $course, string $modname, array $answers, ?int $sectionnum = null): \cm_info {
        return self::as_posted($answers, function () use ($course, $modname, $answers, $sectionnum) {
            [$mform, $fromform, $errors] = self::submit($course, $modname, $answers, $sectionnum);
            if (!$fromform) {
                throw new \moodle_exception('error_moduleform', 'tool_wizards', '', null, json_encode($errors));
            }

            // As course/modedit.php does: a large regrade is queued rather than run in this request.
            $fromform->frontend = true;
            $result = add_moduleinfo($fromform, $course, $mform);
            return get_fast_modinfo($course->id)->get_cm($result->coursemodule);
        });
    }

    /**
     * What the module's own form says about the answers, without creating anything.
     *
     * @param stdClass $course the course
     * @param string $modname the module
     * @param array $answers field => value for the module's own form, as for {@see create()}
     * @param int|null $sectionnum the section, default {@see default_section()}
     * @return array field => error message; empty when the form accepts them
     */
    public static function check(stdClass $course, string $modname, array $answers, ?int $sectionnum = null): array {
        return self::as_posted($answers, function () use ($course, $modname, $answers, $sectionnum) {
            [, , $errors] = self::submit($course, $modname, $answers, $sectionnum);
            return $errors;
        });
    }

    /**
     * Run something with the answers' draft file areas in the request, as a browser posts them.
     *
     * Some activity forms read an uploaded file's draft area from the request rather than from the
     * submitted data (file_get_submitted_draft_itemid() reads $_REQUEST, e.g. the H5P and SCORM
     * package checks in their validation()). A wizard's answers come in a web service call, so the
     * ones that are the current user's draft areas are put in $_POST and $_REQUEST for the time
     * being, and taken out again afterwards.
     *
     * @param array $answers field => value for the module's own form
     * @param callable $fn what to run
     * @return mixed what it returns
     */
    protected static function as_posted(array $answers, callable $fn) {
        global $DB, $USER;
        $saved = [];
        $usercontext = \core\context\user::instance($USER->id)->id;
        foreach ($answers as $field => $value) {
            // Only the user's own draft areas: nothing else from the answers goes into the request.
            if (
                is_int($value) && $value > 0 && !array_key_exists($field, $_POST) && !array_key_exists($field, $_REQUEST)
                    && $DB->record_exists('files', ['contextid' => $usercontext, 'component' => 'user',
                        'filearea' => 'draft', 'itemid' => $value])
            ) {
                $saved[] = $field;
                $_POST[$field] = $value;
                $_REQUEST[$field] = $value;
            }
        }
        try {
            return $fn();
        } finally {
            foreach ($saved as $field) {
                unset($_POST[$field], $_REQUEST[$field]);
            }
        }
    }

    /**
     * The module's own form for a new activity, built as course/modedit.php builds it.
     *
     * @param stdClass $course the course
     * @param string $modname the module
     * @param int|null $sectionnum the section, default {@see default_section()}
     * @return \moodleform
     */
    public static function form(stdClass $course, string $modname, ?int $sectionnum = null): \moodleform {
        return self::factory($course, $modname, $sectionnum)();
    }

    /**
     * Submit the answers to the module's own form.
     *
     * @param stdClass $course the course
     * @param string $modname the module
     * @param array $answers field => value for the module's own form
     * @param int|null $sectionnum the section, default {@see default_section()}
     * @return array [\moodleform $mform, \stdClass|null $fromform, array $errors]
     */
    protected static function submit(stdClass $course, string $modname, array $answers, ?int $sectionnum = null): array {
        $factory = self::factory($course, $modname, $sectionnum, (int) ($answers['typeid'] ?? 0));
        $values = form_submission::browser_values($factory(), $answers);
        return form_submission::submit($factory, $values);
    }

    /**
     * A function that builds the module's form as course/modedit.php does for "add".
     *
     * @param stdClass $course the course
     * @param string $modname the module
     * @param int|null $sectionnum the section, default {@see default_section()}
     * @param int $typeid for an external tool, the chosen tool
     * @return callable
     */
    protected static function factory(stdClass $course, string $modname, ?int $sectionnum = null, int $typeid = 0): callable {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/formslib.php');

        // Ask core before anything else: can_add_moduleinfo() would create a missing section
        // before finding out the module is not allowed.
        if (!self::is_available($course, $modname)) {
            throw new \moodle_exception('error_notavailable', 'tool_wizards');
        }
        // Activities that never show on the course page (question banks) go in section 0, as core adds them.
        $sectionnum ??= plugin_supports('mod', $modname, FEATURE_CAN_DISPLAY, true) ? self::default_section($course) : 0;
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
        if ($modname === 'lti' && $typeid) {
            // As the activity chooser does: the tool comes in the request, which the external tool's form reads.
            if (!in_array($typeid, array_map(fn($tool) => (int) $tool->id, self::lti_tools($course)), true)) {
                throw new \moodle_exception('error_notavailable', 'tool_wizards');
            }
            $data->typeid = $typeid;
        }
        return function () use ($classname, $data, $cw, $cm, $course, $typeid) {
            $saved = $_GET['typeid'] ?? null;
            if ($typeid) {
                $_GET['typeid'] = $typeid;
            }
            try {
                $form = new $classname(clone $data, $cw->section, $cm, $course);
            } finally {
                if ($saved === null) {
                    unset($_GET['typeid']);
                } else {
                    $_GET['typeid'] = $saved;
                }
            }
            $form->set_data(clone $data);
            return $form;
        };
    }
}
