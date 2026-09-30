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

namespace tool_wizards\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use tool_wizards\local\course_creator;
use tool_wizards\local\course_wizard as wizard;

/**
 * The course wizard page: one form, one question per step.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_wizard implements renderable, templatable {
    /**
     * Constructor.
     *
     * @param int $categoryid the category the wizard starts in
     * @param \stdClass|null $answers answers already given (after a failed submission), or null
     * @param array $errors field => error message
     * @param string $startstep the step to open first ('' for the first one)
     */
    public function __construct(
        /** @var int the category the wizard starts in */
        protected int $categoryid,
        /** @var \stdClass|null answers already given */
        protected ?\stdClass $answers,
        /** @var array field => error message */
        protected array $errors,
        /** @var string the step to open first */
        protected string $startstep,
    ) {
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output the renderer
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $courseconfig = get_config('moodlecourse');
        $answers = $this->answers ?? new \stdClass();

        $formats = course_creator::get_formats();
        $formatnames = array_column($formats, 'name');
        $format = $answers->format ?? '';
        if (!in_array($format, $formatnames, true)) {
            $format = in_array($courseconfig->format ?? '', $formatnames, true) ? $courseconfig->format : ($formatnames[0] ?? '');
        }
        foreach ($formats as &$f) {
            $f['checked'] = $f['name'] === $format;
            $f['id'] = 'tool_wizards_format_' . $f['name'];
        }
        unset($f);

        $selectedcategory = isset($answers->category) && $answers->category ? $answers->category : $this->categoryid;
        $categories = [];
        foreach (course_creator::get_categories() as $id => $name) {
            $categories[] = [
                'id' => $id,
                // Already run through format_string() by make_categories_list(); the template escapes.
                'name' => html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'selected' => $id == $selectedcategory,
                'canvisibility' => course_creator::can_choose_visibility($id),
            ];
        }
        $canvisibility = course_creator::can_choose_visibility($selectedcategory);

        $numsections = $answers->numsections ?? (int) ($courseconfig->numsections ?? 4);
        $startdate = $answers->startdate ?? (usergetmidnight(time()) + DAYSECS);
        $visible = isset($answers->visible) ? (bool) $answers->visible : !empty($courseconfig->visible);

        $errors = [];
        foreach ($this->errors as $field => $message) {
            $errors[$field] = $message;
        }
        $othererrors = [];
        foreach ($this->errors as $field => $message) {
            if (wizard::step_for_error($field) === 'review') {
                $othererrors[] = ['message' => $message];
            }
        }

        return [
            'actionurl' => (new \moodle_url('/admin/tool/wizards/course.php'))->out(false),
            'sesskey' => sesskey(),
            'startstep' => $this->startstep ?: 'name',
            'fullname' => $answers->fullname ?? '',
            'shortname' => $answers->shortname ?? '',
            'fullnameerror' => $errors['fullname'] ?? '',
            'shortnameerror' => $errors['shortname'] ?? '',
            'categoryerror' => $errors['category'] ?? '',
            'formaterror' => $errors['format'] ?? '',
            'numsectionserror' => $errors['numsections'] ?? '',
            'startdateerror' => $errors['startdate'] ?? ($errors['enddate'] ?? ''),
            'othererrors' => $othererrors,
            'hasothererrors' => !empty($othererrors),
            'askcategory' => count($categories) > 1,
            'categories' => $categories,
            'categoryid' => $selectedcategory,
            'formats' => $formats,
            'numsections' => $numsections,
            'maxsections' => course_creator::get_max_sections($format),
            'startdate' => wizard::format_date($startdate),
            'canvisibility' => $canvisibility,
            'visiblenow' => $visible,
            'visiblelater' => !$visible,
            'showstartedtask' => course_creator::show_started_courses_enabled(),
        ];
    }
}
