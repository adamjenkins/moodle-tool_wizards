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

use advanced_testcase;
use core_course_category;

/**
 * Tests for the course wizard's course creation.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_creator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(course_defaults_form::class)]
final class course_creator_test extends advanced_testcase {
    /** @var string[] Course columns that must differ between two courses, or depend on the moment of creation. */
    const IGNORED_COLUMNS = ['id', 'shortname', 'sortorder', 'timecreated', 'timemodified', 'cacherev', 'originalcourseid'];

    /**
     * Load the libraries the tests use.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/edit_form.php');
        require_once($CFG->libdir . '/enrollib.php');
    }

    /**
     * A user holding the Course creator role in one category, like wizards_creator on a real site.
     *
     * @param core_course_category $category the category
     * @return \stdClass the user
     */
    protected function make_category_creator(core_course_category $category): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'coursecreator'], MUST_EXIST);
        role_assign($roleid, $user->id, \context_coursecat::instance($category->id)->id);
        return $user;
    }

    /**
     * What a browser would submit for a form nobody touched: every control's current value.
     *
     * @param \moodleform $form the form
     * @return array name => value, nested like $_POST
     */
    protected function browser_values(\moodleform $form): array {
        global $PAGE;
        $PAGE->set_url(new \moodle_url('/course/edit.php'));
        $html = $form->render();
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        $pairs = [];
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//input[@name] | //select[@name] | //textarea[@name]') as $el) {
            if ($el->hasAttribute('disabled')) {
                continue;
            }
            $name = $el->getAttribute('name');
            if ($el->nodeName === 'input') {
                $type = strtolower($el->getAttribute('type'));
                if (in_array($type, ['submit', 'button', 'reset', 'image', 'file'], true)) {
                    continue;
                }
                if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                    continue;
                }
                $value = $el->hasAttribute('value') ? $el->getAttribute('value') : ($type === 'checkbox' ? 'on' : '');
                $pairs[] = [$name, $value];
            } else if ($el->nodeName === 'textarea') {
                $pairs[] = [$name, $el->textContent];
            } else {
                $options = $xpath->query('.//option', $el);
                $selected = [];
                foreach ($options as $option) {
                    if ($option->hasAttribute('selected')) {
                        $selected[] = $option->getAttribute('value');
                    }
                }
                if (!$selected && $options->length && !$el->hasAttribute('multiple')) {
                    $selected[] = $options->item(0)->getAttribute('value');
                }
                foreach ($selected as $value) {
                    $pairs[] = [$name, $value];
                }
            }
        }
        $query = implode('&', array_map(fn($p) => rawurlencode($p[0]) . '=' . rawurlencode($p[1]), $pairs));
        parse_str($query, $values);
        return $values;
    }

    /**
     * Create a course the way course/edit.php does: through the core form as filled in
     * by a teacher who only changed the given fields, then edit.php's enrolment step.
     *
     * @param core_course_category $category the category
     * @param array $changes submitted field => value (nested for date selectors)
     * @return \stdClass the new course
     */
    protected function create_with_core_form(core_course_category $category, array $changes): \stdClass {
        global $CFG, $USER;
        $editoroptions = ['maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->maxbytes, 'trusttext' => false,
            'noclean' => true, 'context' => \context_coursecat::instance($category->id), 'subdirs' => 0];
        $course = file_prepare_standard_editor(null, 'summary', $editoroptions, null, 'course', 'summary', null);
        $args = ['course' => $course, 'category' => $category, 'editoroptions' => $editoroptions, 'returnto' => 0,
            'returnurl' => new \moodle_url('/course/')];

        $values = array_replace($this->browser_values(new \course_edit_form(null, $args)), $changes);
        \course_edit_form::mock_submit($values);
        $form = new \course_edit_form(null, $args);
        $data = $form->get_data();
        $this->assertNotNull($data, 'The core form did not validate: ' . json_encode($form->validation((array) $values, [])));

        // The code of course/edit.php, lines 161-179.
        $course = create_course($data, $editoroptions);
        $context = \context_course::instance($course->id, MUST_EXIST);
        if (is_siteadmin($USER->id)) {
            $enroluser = $CFG->enroladminnewcourse;
        } else {
            $enroluser = !is_viewing($context, null, 'moodle/role:assign');
        }
        if (!empty($CFG->creatornewroleid) && $enroluser && !is_enrolled($context, null, 'moodle/role:assign')) {
            enrol_try_internal_enrol($course->id, $USER->id, $CFG->creatornewroleid);
        }
        return $course;
    }

    /**
     * Everything about a course that the two ways of creating it should agree on.
     *
     * @param \stdClass $course the course
     * @return array
     */
    protected function course_facts(\stdClass $course): array {
        global $DB, $USER;
        $record = (array) $DB->get_record('course', ['id' => $course->id]);
        foreach (self::IGNORED_COLUMNS as $column) {
            unset($record[$column]);
        }
        $context = \context_course::instance($course->id);
        $roles = array_map(fn($r) => $r->shortname, get_user_roles($context, $USER->id, false));
        sort($roles);
        return [
            'course' => $record,
            'formatoptions' => $DB->get_records_menu('course_format_options', ['courseid' => $course->id], 'name', 'name, value'),
            'sections' => $DB->count_records('course_sections', ['course' => $course->id]),
            'enrolled' => is_enrolled($context, $USER),
            'roles' => $roles,
            'enrolinstances' => array_values(array_map(
                fn($e) => $e->enrol . ':' . $e->status,
                $DB->get_records('enrol', ['courseid' => $course->id], 'enrol, id')
            )),
            'modules' => array_values(array_map(fn($cm) => $cm->modname, get_fast_modinfo($course->id)->get_cms())),
        ];
    }

    /**
     * Parity cases: [layout, user kind, wizard answers besides the name].
     *
     * @return array
     */
    public static function parity_provider(): array {
        return [
            'custom sections, course creator' => ['topics', 'creator', ['numsections' => 7, 'visible' => 1]],
            'weekly sections, course creator, hidden' => ['weeks', 'creator',
                ['numsections' => 6, 'startdate' => [2026, 10, 5], 'visible' => 0]],
            'custom sections, admin, not enrolled by default' => ['topics', 'admin', ['numsections' => 3]],
            'custom sections, admin enrolling' => ['topics', 'adminenrol', ['numsections' => 2, 'visible' => 0]],
            // Social has no number of sections in the standard form; an answer must not add any.
            'social, course creator, sections answered anyway' => ['social', 'creator', ['numsections' => 5]],
        ];
    }

    /**
     * A course made by the wizard is the course the standard form makes from the same answers.
     *
     * @param string $format the layout
     * @param string $who creator, admin or adminenrol
     * @param array $answers answers besides the name, layout and category
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('parity_provider')]
    public function test_matches_core_form(string $format, string $who, array $answers): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        if ($who === 'creator') {
            $this->setUser($this->make_category_creator($category));
        } else {
            $this->setAdminUser();
            set_config('enroladminnewcourse', $who === 'adminenrol' ? 1 : 0);
        }
        // The standard form's own layout choice reloads the page with that layout's options, which
        // cannot be clicked here; making the layout the site default gives the page it reloads to.
        set_config('format', $format, 'moodlecourse');
        \core\plugininfo\format::enable_plugin($format, 1);

        $startdate = isset($answers['startdate']) ? make_timestamp(...$answers['startdate']) : null;
        $core = $this->create_with_core_form($category, array_filter([
            'fullname' => 'Parity course',
            'shortname' => 'PARITY-CORE',
            'format' => $format,
            'numsections' => $answers['numsections'] ?? null,
            'visible' => $answers['visible'] ?? null,
            // The date as the teacher picks it in the form's day, month and year menus.
            'startdate' => isset($answers['startdate']) ? ['day' => $answers['startdate'][2],
                'month' => $answers['startdate'][1], 'year' => $answers['startdate'][0]] : null,
        ], fn($v) => $v !== null));

        $wizard = course_creator::create(course_creator::normalise_answers([
            'fullname' => 'Parity course',
            'shortname' => 'PARITY-WIZARD',
            'category' => $category->id,
            'format' => $format,
            'numsections' => $answers['numsections'] ?? '',
            'startdate' => $startdate,
            'visible' => $answers['visible'] ?? '',
        ]));

        $expected = $this->course_facts($core);
        $actual = $this->course_facts($wizard['course']);
        $this->assertEquals($expected, $actual);
        $this->assertSame($expected['enrolled'] || is_viewing(\context_course::instance($core->id)), $wizard['enrolled']);
        if ($who === 'creator') {
            // The point of copying edit.php's enrolment: a course creator becomes the teacher.
            $this->assertTrue($actual['enrolled']);
            $this->assertSame(['editingteacher'], $actual['roles']);
        }
        if ($who === 'admin') {
            $this->assertFalse($actual['enrolled']);
        }
        if ($format === 'weeks') {
            $this->assertEquals($startdate, $actual['course']['startdate']);
            $this->assertEquals(7, $actual['sections']);
        }
        $this->assertCount(0, array_diff(['forum'], $actual['modules']), 'Both have the Announcements forum.');
    }

    /**
     * The wizard refuses a category where the user cannot create courses, even if the id is forged.
     */
    public function test_create_refuses_other_category(): void {
        $this->resetAfterTest();
        $mine = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_category();
        $this->setUser($this->make_category_creator($mine));

        $answers = course_creator::normalise_answers(['fullname' => 'Nope', 'shortname' => 'NOPE',
            'category' => $other->id, 'format' => 'topics']);
        $this->expectException(\required_capability_exception::class);
        course_creator::create($answers);
    }

    /**
     * Validation reports a taken short name through core's own form validation.
     */
    public function test_validate_reports_taken_shortname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $existing = $this->getDataGenerator()->create_course(['shortname' => 'TAKEN']);

        $errors = course_creator::validate(course_creator::normalise_answers(['fullname' => 'A course',
            'shortname' => 'TAKEN', 'category' => $category->id, 'format' => 'topics']));
        $this->assertSame(['shortname'], array_keys($errors));
        $this->assertSame(get_string('shortnametaken', '', $existing->fullname), $errors['shortname']);
    }

    /**
     * Validation of the wizard's own questions.
     */
    public function test_validate_own_questions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $errors = course_creator::validate(course_creator::normalise_answers(['fullname' => '', 'shortname' => '',
            'category' => -1, 'format' => 'nosuchformat']));
        $this->assertEqualsCanonicalizing(['fullname', 'shortname', 'category', 'format'], array_keys($errors));
    }

    /**
     * Answers that do not apply to the chosen layout are dropped, and numbers are kept in range.
     */
    public function test_normalise_answers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();

        $weeks = course_creator::normalise_answers(['fullname' => 'W', 'shortname' => 'W', 'category' => $category->id,
            'format' => 'weeks', 'numsections' => 999, 'startdate' => 12345, 'visible' => '0']);
        $this->assertSame(course_creator::get_max_sections('weeks'), $weeks->numsections);
        $this->assertSame(12345, $weeks->startdate);
        $this->assertSame(0, $weeks->visible);

        $topics = course_creator::normalise_answers(['fullname' => 'T', 'shortname' => 'T', 'category' => $category->id,
            'format' => 'topics', 'numsections' => 3, 'startdate' => 12345]);
        $this->assertSame(3, $topics->numsections);
        $this->assertObjectNotHasProperty('startdate', $topics, 'Only weekly sections ask for a start date.');
        $this->assertObjectNotHasProperty('visible', $topics, 'Unanswered visibility falls back to the site default.');
    }

    /**
     * With core's "show courses on their start date" task on, "later" asks for the start date for
     * every layout, since that is when students will see the course.
     */
    public function test_startdate_asked_when_later_means_start_date(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $raw = ['fullname' => 'T', 'shortname' => 'T', 'category' => $category->id, 'format' => 'topics',
            'startdate' => 12345, 'visible' => '0'];
        $this->assertObjectNotHasProperty('startdate', course_creator::normalise_answers($raw), 'Task off: not asked.');

        $task = \core\task\manager::get_scheduled_task(\core\task\show_started_courses_task::class);
        $task->set_disabled(false);
        \core\task\manager::configure_scheduled_task($task);
        $this->assertSame(12345, course_creator::normalise_answers($raw)->startdate);
        $raw['visible'] = '1';
        $this->assertObjectNotHasProperty('startdate', course_creator::normalise_answers($raw), 'Visible now: not asked.');
    }

    /**
     * A required course custom field, which the standard form enforces, stops the wizard too.
     */
    public function test_required_custom_field_enforced(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $cfcategory = $generator->create_category();
        $generator->create_field(['categoryid' => $cfcategory->get('id'), 'type' => 'text', 'shortname' => 'dept',
            'name' => 'Department', 'configdata' => ['required' => 1, 'defaultvalue' => '']]);
        $category = $this->getDataGenerator()->create_category();

        $errors = course_creator::validate(course_creator::normalise_answers(['fullname' => 'Needs dept',
            'shortname' => 'NEEDSDEPT', 'category' => $category->id, 'format' => 'topics']));
        $this->assertArrayHasKey('customfield_dept', $errors);
        $this->assertSame(get_string('error_requiredsetting', 'tool_wizards', 'Department'), $errors['customfield_dept']);
        // No wizard question sets it, so the wizard shows it at the top of its first screen.
        global $CFG;
        $doc = json_decode(file_get_contents($CFG->dirroot . '/admin/tool/wizards/defaults/course.json'), true);
        $engine = new \tool_wizards\local\wizard\engine($doc, 0, null, $category->id);
        $placed = $engine->place_errors($errors);
        $this->assertStringContainsString($errors['customfield_dept'], $placed['wizarderrors'] ?? '');
    }

    /**
     * Without an answer, visibility follows the site's default, as in the standard form; create_course()
     * on its own would use the category's visibility instead.
     */
    public function test_unanswered_visibility_uses_site_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('visible', 0, 'moodlecourse');
        $category = $this->getDataGenerator()->create_category(['visible' => 1]);
        $result = course_creator::create(course_creator::normalise_answers(['fullname' => 'Default visibility',
            'shortname' => 'DEFVIS', 'category' => $category->id, 'format' => 'topics']));
        $this->assertEquals(0, $result['course']->visible);
    }

    /**
     * Visibility is only taken from the answers when the creator will be allowed to set it.
     */
    public function test_visibility_ignored_without_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $this->setUser($this->make_category_creator($category));
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:visibility', CAP_PROHIBIT, $teacherrole, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(course_creator::can_choose_visibility($category->id));

        $answers = course_creator::normalise_answers(['fullname' => 'V', 'shortname' => 'V', 'category' => $category->id,
            'format' => 'topics', 'visible' => '0']);
        $this->assertObjectNotHasProperty('visible', $answers);
    }

    /**
     * Short name suggestions: initials and year for Latin names, the name itself otherwise, and never a taken one.
     */
    public function test_suggest_shortname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $year = userdate(time(), '%Y');
        $this->assertSame('ITB-' . $year, course_creator::suggest_shortname('Introduction to Biology'));
        $this->assertSame('生物学入門', course_creator::suggest_shortname('生物学入門'));
        $this->assertSame('', course_creator::suggest_shortname('   '));

        $this->getDataGenerator()->create_course(['shortname' => 'ITB-' . $year]);
        $this->assertSame('ITB-' . $year . ' 2', course_creator::suggest_shortname('Introduction to Biology'));

        // A missing short name is suggested on the server too, for browsers without JavaScript.
        $answers = course_creator::normalise_answers(['fullname' => 'Introduction to Biology', 'shortname' => '',
            'category' => 1, 'format' => 'topics']);
        $this->assertSame('ITB-' . $year . ' 2', $answers->shortname);
    }

    /**
     * The start category: the requested one when allowed, never one the user cannot create in.
     */
    public function test_get_start_category(): void {
        $this->resetAfterTest();
        $mine = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_category();
        $this->setUser($this->make_category_creator($mine));
        $this->assertSame((int) $mine->id, course_creator::get_start_category($mine->id));
        $this->assertSame((int) $mine->id, course_creator::get_start_category($other->id));
        $this->assertSame((int) $mine->id, course_creator::get_start_category(0));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNull(course_creator::get_start_category(0));
    }

    /**
     * The wizard leaves out layouts that need questions it does not ask.
     */
    public function test_get_formats_excludes_singleactivity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $names = array_column(course_creator::get_formats(), 'name');
        $this->assertNotContains('singleactivity', $names);
        $this->assertContains('topics', $names);
        $weeks = array_values(array_filter(course_creator::get_formats(), fn($f) => $f['name'] === 'weeks'))[0];
        $this->assertTrue($weeks['usesstartdate']);
        $this->assertTrue($weeks['usessections']);
    }
}
