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

use core_course_category;
use stdClass;

/**
 * Creates a course from the course wizard's answers, the way course/edit.php does.
 *
 * Every value the wizard does not ask about comes from core's own course form
 * ({@see course_defaults_form}), the course is created with create_course(), and
 * the creator is enrolled with the same code course/edit.php runs afterwards.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_creator {
    /** @var string[] Formats the wizard does not offer (they need questions it does not ask). */
    const EXCLUDED_FORMATS = ['singleactivity'];

    /** @var int Longest suggested short name, in characters. */
    const SHORTNAME_SUGGESTION_LENGTH = 20;

    /**
     * Categories where the current user may create courses.
     *
     * @return array category id => display name (already formatted)
     */
    public static function get_categories(): array {
        return core_course_category::make_categories_list('moodle/course:create');
    }

    /**
     * Pick the category the wizard starts in.
     *
     * The requested one if the user may create courses there, otherwise the one
     * block_myoverview would link its "Create course" button to, otherwise the first
     * allowed one. Never core_course_category::get_default(), which checks no permission.
     *
     * @param int $requested category id from the URL, or 0
     * @return int|null category id, or null when the user can create courses nowhere
     */
    public static function get_start_category(int $requested): ?int {
        $allowed = self::get_categories();
        if (!$allowed) {
            return null;
        }
        if ($requested && isset($allowed[$requested])) {
            return $requested;
        }
        $nearest = core_course_category::get_nearest_editable_subcategory(core_course_category::user_top(), ['create']);
        if ($nearest && isset($allowed[$nearest->id])) {
            return (int) $nearest->id;
        }
        return (int) array_key_first($allowed);
    }

    /**
     * The course formats the wizard offers, in the site's order.
     *
     * @return array of ['name' => string, 'label' => string, 'description' => string,
     *                   'usessections' => bool, 'usesstartdate' => bool, 'maxsections' => int]
     */
    public static function get_formats(): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $formats = [];
        foreach (get_sorted_course_formats(true) as $name) {
            if (in_array($name, self::EXCLUDED_FORMATS, true)) {
                continue;
            }
            $format = course_get_format((object) ['format' => $name]);
            $sm = get_string_manager();
            if ($sm->string_exists('formatdesc_' . $name, 'tool_wizards')) {
                $description = get_string('formatdesc_' . $name, 'tool_wizards');
            } else if ($sm->string_exists('plugin_description', 'format_' . $name)) {
                $description = get_string('plugin_description', 'format_' . $name);
            } else {
                $description = '';
            }
            $formats[] = [
                'name' => $name,
                'label' => get_string('pluginname', 'format_' . $name),
                'description' => $description,
                // Only formats whose new-course form asks for a number of sections: core's topics
                // and weeks add a numsections element (course/format/{topics,weeks}/lib.php
                // create_edit_form_elements()); social does not, although it uses_sections().
                'usessections' => in_array($name, ['topics', 'weeks'], true)
                    || array_key_exists('numsections', $format->course_format_options()),
                'usesstartdate' => $format instanceof \format_weeks,
                'maxsections' => self::get_max_sections($name),
            ];
        }
        return $formats;
    }

    /**
     * The largest number of sections the wizard lets the teacher choose for a format.
     *
     * @param string $format the format name
     * @return int
     */
    public static function get_max_sections(string $format): int {
        if ($format === 'weeks') {
            $max = (int) get_config('format_weeks', 'maxinitialsections');
            if ($max > 0) {
                return $max;
            }
        }
        $max = (int) get_config('moodlecourse', 'maxsections');
        return $max > 0 ? $max : 52;
    }

    /**
     * Whether the creator will be able to change the course visibility in this category.
     *
     * Mirrors course/edit_form.php, which freezes the visibility field otherwise.
     *
     * @param int $categoryid the category
     * @return bool
     */
    public static function can_choose_visibility(int $categoryid): bool {
        return guess_if_creator_will_have_course_capability(
            'moodle/course:visibility',
            \core\context\coursecat::instance($categoryid)
        );
    }

    /**
     * Suggest an unused short name for a course full name.
     *
     * Latin words give their initials plus the year ("Introduction to Biology" becomes
     * "ITB-2026"); a name with no Latin letters (for example Japanese) is truncated
     * instead, because short names may be any text. A number is added until the
     * suggestion is free.
     *
     * @param string $fullname the full name
     * @return string the suggestion, or '' for an empty name
     */
    public static function suggest_shortname(string $fullname): string {
        $fullname = trim(strip_tags($fullname));
        if ($fullname === '') {
            return '';
        }
        $initials = '';
        if (preg_match_all('/[A-Za-z0-9]+/u', $fullname, $matches)) {
            foreach ($matches[0] as $word) {
                $initials .= ctype_digit($word) ? $word : \core_text::strtoupper($word[0]);
            }
        }
        if (preg_match('/[A-Za-z]/', $initials)) {
            $base = $initials . '-' . userdate(time(), '%Y');
        } else {
            $base = \core_text::substr($fullname, 0, self::SHORTNAME_SUGGESTION_LENGTH);
        }
        $base = \core_text::substr($base, 0, self::SHORTNAME_SUGGESTION_LENGTH);
        return self::make_unique_shortname($base);
    }

    /**
     * Add " 2", " 3"... to a short name until no course uses it.
     *
     * @param string $shortname the preferred short name
     * @return string
     */
    public static function make_unique_shortname(string $shortname): string {
        global $DB;
        $candidate = $shortname;
        $n = 1;
        while ($DB->record_exists('course', ['shortname' => $candidate])) {
            $n++;
            $candidate = $shortname . ' ' . $n;
        }
        return $candidate;
    }

    /**
     * Turn the wizard's raw answers into the values it will use, applying the
     * wizard's own rules (which steps apply to which format).
     *
     * @param array $answers raw answers: fullname, shortname, category, format, numsections,
     *                       startdate (timestamp), visible
     * @return stdClass the normalised answers
     */
    public static function normalise_answers(array $answers): stdClass {
        $out = new stdClass();
        $out->fullname = trim((string) ($answers['fullname'] ?? ''));
        $out->shortname = trim((string) ($answers['shortname'] ?? ''));
        if ($out->shortname === '' && $out->fullname !== '') {
            // Without JavaScript nobody saw a suggestion, so make one now.
            $out->shortname = self::suggest_shortname($out->fullname);
        }
        $out->category = (int) ($answers['category'] ?? 0);
        $out->format = (string) ($answers['format'] ?? '');

        $formatinfo = null;
        foreach (self::get_formats() as $f) {
            if ($f['name'] === $out->format) {
                $formatinfo = $f;
            }
        }
        if ($formatinfo && $formatinfo['usessections'] && isset($answers['numsections']) && $answers['numsections'] !== '') {
            $out->numsections = max(0, min((int) $answers['numsections'], $formatinfo['maxsections']));
        }
        if (
            $out->category && isset($answers['visible']) && $answers['visible'] !== ''
                && self::can_choose_visibility($out->category)
        ) {
            $out->visible = empty($answers['visible']) ? 0 : 1;
        }
        // The start date is asked for weekly sections, and whenever "show it later" means
        // "on its start date" (core's show_started_courses_task is on).
        if ($formatinfo && !empty($answers['startdate']) && self::asks_startdate($formatinfo, $out->visible ?? null)) {
            $out->startdate = (int) $answers['startdate'];
        }
        return $out;
    }

    /**
     * Whether the wizard asks for a start date.
     *
     * @param array $formatinfo the chosen format (see get_formats())
     * @param int|null $visible the visibility answer, or null when not asked
     * @return bool
     */
    public static function asks_startdate(array $formatinfo, ?int $visible): bool {
        return $formatinfo['usesstartdate'] || ($visible === 0 && self::show_started_courses_enabled());
    }

    /**
     * Whether core's "show courses on their start date" task is switched on.
     *
     * @return bool
     */
    public static function show_started_courses_enabled(): bool {
        $task = \core\task\manager::get_scheduled_task(\core\task\show_started_courses_task::class);
        return $task && !$task->get_disabled();
    }

    /**
     * Check the answers. The caller must already have checked moodle/course:create
     * in the chosen category.
     *
     * @param stdClass $answers normalised answers
     * @return array field => error message; empty when everything is fine
     */
    public static function validate(stdClass $answers): array {
        $errors = [];
        if ($answers->fullname === '') {
            $errors['fullname'] = get_string('error_fullname', 'tool_wizards');
        } else if (\core_text::strlen($answers->fullname) > \core_course\constants::FULLNAME_MAXIMUM_LENGTH) {
            $errors['fullname'] = get_string('maximumchars', '', \core_course\constants::FULLNAME_MAXIMUM_LENGTH);
        }
        if ($answers->shortname === '') {
            $errors['shortname'] = get_string('error_shortname', 'tool_wizards');
        }
        if (!isset(self::get_categories()[$answers->category])) {
            $errors['category'] = get_string('error_category', 'tool_wizards');
        }
        $formatnames = array_column(self::get_formats(), 'name');
        if (!in_array($answers->format, $formatnames, true)) {
            $errors['format'] = get_string('error_format', 'tool_wizards');
        }
        if ($errors) {
            return $errors;
        }

        // Everything else is checked by core's own form: its required fields (such as a required
        // course custom field, which the wizard cannot ask about) and its validation.
        [$form, $data] = self::build_core_data($answers);
        $coreerrors = $form->validation($data, []);
        return array_merge($form->required_errors($data), is_array($coreerrors) ? $coreerrors : []);
    }

    /**
     * Create the course and enrol its creator. Checks moodle/course:create in the category.
     *
     * @param stdClass $answers normalised answers (see normalise_answers())
     * @return array ['course' => stdClass, 'enrolled' => bool]
     * @throws \moodle_exception when the answers do not validate
     */
    public static function create(stdClass $answers): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        require_capability('moodle/course:create', \core\context\coursecat::instance($answers->category));

        $errors = self::validate($answers);
        if ($errors) {
            throw new \moodle_exception('error_invalid', 'tool_wizards', '', null, json_encode($errors));
        }

        [, $data, $editoroptions] = self::build_core_data($answers);
        $course = create_course((object) $data, $editoroptions);

        // What follows is course/edit.php's own post-creation enrolment, kept line for line
        // (public/course/edit.php:163-179 in Moodle 5.2), including its deliberate disregard
        // of capabilities (MDL-66683).
        $context = \context_course::instance($course->id, MUST_EXIST);

        // Admins have all capabilities, so is_viewing is returning true for admins.
        // We are checking 'enroladminnewcourse' setting to decide to enrol them or not.
        if (is_siteadmin($USER->id)) {
            $enroluser = $CFG->enroladminnewcourse;
        } else {
            $enroluser = !is_viewing($context, null, 'moodle/role:assign');
        }

        if (!empty($CFG->creatornewroleid) && $enroluser && !is_enrolled($context, null, 'moodle/role:assign')) {
            // Deal with course creators - enrol them internally with default role.
            // Note: This does not respect capabilities, the creator will be assigned the default role.
            // This is an expected behaviour. See MDL-66683 for further details.
            enrol_try_internal_enrol($course->id, $USER->id, $CFG->creatornewroleid);
        }
        $enrolled = self::creator_can_enter($context);

        return ['course' => $course, 'enrolled' => $enrolled];
    }

    /**
     * Whether the creator can get into the new course afterwards: enrolled, or viewing
     * without enrolment (admins, managers).
     *
     * @param \context_course $context the new course's context
     * @return bool
     */
    protected static function creator_can_enter(\context_course $context): bool {
        return is_enrolled($context) || is_viewing($context);
    }

    /**
     * Build the data array core's form would submit for these answers.
     *
     * @param stdClass $answers normalised answers
     * @return array [course_defaults_form $form, array $data, array $editoroptions]
     */
    protected static function build_core_data(stdClass $answers): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/formslib.php');

        $category = core_course_category::get($answers->category, MUST_EXIST, true);
        $catcontext = \core\context\coursecat::instance($category->id);

        // The same editor options and empty-course preparation as course/edit.php.
        $editoroptions = [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'trusttext' => false,
            'noclean' => true,
            'context' => $catcontext,
            'subdirs' => 0,
        ];
        $course = file_prepare_standard_editor(null, 'summary', $editoroptions, null, 'course', 'summary', null);

        // The wizard's answers become the form's starting values, as if typed in.
        $course->category = $category->id;
        foreach (['fullname', 'shortname', 'format', 'numsections', 'startdate', 'visible'] as $field) {
            if (isset($answers->$field)) {
                $course->$field = $answers->$field;
            }
        }

        $form = new course_defaults_form(null, [
            'course' => $course,
            'category' => $category,
            'editoroptions' => $editoroptions,
            'returnto' => 0,
            'returnurl' => new \moodle_url($CFG->wwwroot . '/course/'),
        ]);
        // The form's definition calls set_data($course) itself, as for course/edit.php.
        $data = $form->get_default_data();
        // Values the form would not carry as element defaults.
        $data['category'] = $category->id;
        foreach (['fullname', 'shortname', 'format', 'numsections', 'startdate', 'visible'] as $field) {
            // Only fields the form has: a format without a number of sections gets none.
            if (isset($answers->$field) && $form->has_element($field)) {
                $data[$field] = $answers->$field;
            }
        }
        return [$form, $data, $editoroptions];
    }
}
