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

use advanced_testcase;
use core_form\external\dynamic_form as dynamic_form_ws;
use tool_wizards\local\wizard\repository;

/**
 * The activity wizards, driven through core's dynamic form web service as the modal drives them.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(wizard_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_wizards\local\wizard\form_builder::class)]
final class mini_wizards_test extends advanced_testcase {
    /** @var string[] The default activity wizards. */
    const DEFAULTS = ['file', 'slides', 'picture', 'page', 'forum', 'glossary', 'quiz', 'assignment', 'link', 'folder',
        'book', 'choice', 'feedback', 'database', 'wiki', 'lesson', 'workshop', 'h5p', 'scorm', 'contentpackage',
        'externaltool', 'bigbluebutton', 'subsection'];

    /** @var string[] The core activities and resources a teacher can add, each of which has a default wizard. */
    const CORE_MODULES = ['assign', 'bigbluebuttonbn', 'book', 'choice', 'data', 'feedback', 'folder', 'forum', 'glossary',
        'h5pactivity', 'imscp', 'label', 'lesson', 'lti', 'page', 'quiz', 'resource', 'scorm', 'subsection', 'url', 'wiki',
        'workshop'];

    /** @var \stdClass the course */
    protected \stdClass $course;

    /** @var \stdClass an editing teacher */
    protected \stdClass $teacher;

    /**
     * A course and its teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);
        // An AJAX request carries the session key itself, which confirm_sesskey() reads.
        $_POST['sesskey'] = sesskey();
    }

    /**
     * Submit a wizard.
     *
     * @param string $wizard the wizard key
     * @param array $values the answers
     * @return array the web service result
     */
    protected function submit(string $wizard, array $values): array {
        $values += ['courseid' => $this->course->id, 'wizard' => $wizard, 'sesskey' => sesskey(),
            '_qf__tool_wizards_form_wizard_form' => 1];
        return dynamic_form_ws::execute(wizard_form::class, http_build_query($values));
    }

    /**
     * Every default activity wizard loads for the teacher, with its screens and the stepper.
     */
    public function test_every_default_loads(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        // The external tool wizard needs a tool set up for the course.
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types(['name' => 'A tool',
            'baseurl' => 'https://example.com/lti', 'coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
            'state' => LTI_TOOL_STATE_CONFIGURED]);
        $this->setUser($this->teacher);
        $_POST['sesskey'] = sesskey();
        foreach (self::DEFAULTS as $key) {
            if ($key === 'bigbluebutton') {
                // Its form asks the meeting server about the site, which a unit test cannot reach.
                continue;
            }
            $result = dynamic_form_ws::execute(wizard_form::class, 'wizard=' . $key . '&courseid=' . $this->course->id);
            $this->assertFalse($result['submitted'], $key);
            $this->assertStringContainsString('data-step="basics"', $result['html'], $key);
            $this->assertStringContainsString('tool_wizards/modal_stepper', $result['javascript'], $key);
            $this->assertStringNotContainsString('[[', $result['html'], "$key: no missing strings");
        }
    }

    /**
     * Every core activity and resource has a default wizard.
     */
    public function test_every_core_module_has_a_wizard(): void {
        $targets = [];
        foreach (\tool_wizards\local\wizard\defaults::shipped() as $doc) {
            $targets[] = $doc['target']['modname'] ?? null;
        }
        foreach (self::CORE_MODULES as $modname) {
            $this->assertContains($modname, $targets, $modname);
        }
        // Nothing addable in core is missing from the list above.
        $standard = \core_plugin_manager::standard_plugins_list('mod');
        foreach ($standard as $modname) {
            if (plugin_supports('mod', $modname, FEATURE_CAN_DISPLAY, true) && $modname !== 'qbank') {
                $this->assertContains($modname, self::CORE_MODULES, $modname);
            }
        }
    }

    /**
     * "Add with a wizard" is offered on a quiz's questions page, with the quiz's in-activity wizards, and
     * not on its other pages.
     */
    public function test_content_link_on_activity_page(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $cm = get_fast_modinfo($this->course)->get_cm($quiz->cmid);
        $html = function (string $pagetype) use ($cm): string {
            global $PAGE;
            $PAGE = new \moodle_page();
            $PAGE->set_url(new \moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id]));
            $PAGE->set_cm($cm, $this->course);
            $PAGE->set_pagetype($pagetype);
            $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
            \tool_wizards\hook_callbacks::before_footer($hook);
            return $hook->get_output();
        };
        $output = $html('mod-quiz-edit');
        $this->assertStringContainsString('tool_wizards-contentlist', $output);
        $this->assertStringContainsString('data-wizard="quizquestion"', $output);
        $this->assertStringNotContainsString('tool_wizards-contentlist', $html('mod-quiz-view'));
    }

    /**
     * Submitting the forum wizard creates the forum.
     */
    public function test_submit_forum(): void {
        global $DB;
        $result = $this->submit('forum', ['name' => 'Week 1 discussion', 'description' => 'Say hello', 'purpose' => 'general']);
        $this->assertTrue($result['submitted'], strip_tags($result['html'] ?? ''));
        $data = json_decode($result['data']);
        $this->assertSame('Week 1 discussion', $data->name);
        $this->assertTrue($DB->record_exists('forum', ['course' => $this->course->id, 'name' => 'Week 1 discussion']));
    }

    /**
     * Each wizard creates its activity from the essentials alone, for each purpose.
     *
     * @param string $wizard the wizard key
     * @param string $modname the module it creates
     * @param array $answers the answers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('essentials_provider')]
    public function test_essentials_create(string $wizard, string $modname, array $answers): void {
        global $DB;
        $result = $this->submit($wizard, $answers + ['name' => 'Made by the ' . $wizard . ' wizard']);
        $this->assertTrue($result['submitted'], $wizard . ': ' . strip_tags($result['html'] ?? ''));
        $name = 'Made by the ' . $wizard . ' wizard';
        $this->assertTrue($DB->record_exists($modname, ['course' => $this->course->id, 'name' => $name]));
    }

    /**
     * The essentials of the wizards for the other core activities, one case per purpose.
     *
     * @return array
     */
    public static function essentials_provider(): array {
        $cases = [];
        $purposes = [
            ['assignment', 'assign', ['essay', 'online', 'paper'], []],
            ['link', 'url', ['read', 'watch'], ['externalurl' => 'https://example.com/article']],
            ['folder', 'folder', ['page', 'inline'], []],
            ['book', 'book', ['textbook', 'handbook', 'collection'], []],
            ['choice', 'choice', ['vote', 'signup', 'check', 'wishes'],
                ['description' => 'Which day suits you?', 'option1' => 'Monday', 'option2' => 'Tuesday']],
            ['feedback', 'feedback', ['evaluation', 'opinion', 'collect', 'checkin'], []],
            ['database', 'data', ['collection', 'showcase', 'checked', 'oneeach'], []],
            ['wiki', 'wiki', ['class', 'individual'], ['firstpagetitle' => 'Start here']],
            ['lesson', 'lesson', ['tutorial', 'branching', 'graded'], []],
            ['workshop', 'workshop', ['writing', 'projects', 'self'], []],
        ];
        foreach ($purposes as [$wizard, $modname, $values, $answers]) {
            foreach ($values as $purpose) {
                $cases["$wizard: $purpose"] = [$wizard, $modname, $answers + ['purpose' => $purpose]];
            }
        }
        $cases['subsection'] = ['subsection', 'subsection', []];
        return $cases;
    }

    /**
     * The wizards that upload a package pass it on to the activity's own file field.
     *
     * @param string $wizard the wizard key
     * @param string $modname the module it creates
     * @param string $fixture the package, relative to $CFG->dirroot
     * @param array $answers the other answers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('package_provider')]
    public function test_package_wizards(string $wizard, string $modname, string $fixture, array $answers): void {
        global $CFG, $DB, $USER;
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_pathname([
            'contextid' => \core\context\user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => basename($fixture),
        ], $CFG->dirroot . '/' . $fixture);
        $result = $this->submit($wizard, $answers + ['name' => 'Package', 'packagefile' => $draftid, 'package' => $draftid]);
        $this->assertTrue($result['submitted'], $wizard . ': ' . strip_tags($result['html'] ?? ''));
        $this->assertTrue($DB->record_exists($modname, ['course' => $this->course->id, 'name' => 'Package']));
    }

    /**
     * The package wizards.
     *
     * @return array
     */
    public static function package_provider(): array {
        return [
            'h5p' => ['h5p', 'h5pactivity', 'h5p/tests/fixtures/filltheblanks.h5p', ['purpose' => 'practice']],
            'scorm' => ['scorm', 'scorm', 'mod/scorm/tests/packages/singlescobasic.zip', ['purpose' => 'learn']],
            'content package' => ['contentpackage', 'imscp', 'mod/imscp/tests/packages/singlescobasic.zip', []],
        ];
    }

    /**
     * The external tool wizard offers the tools set up for the course, and is not offered where there are none.
     */
    public function test_external_tool(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        $this->assertFalse(\tool_wizards\local\module_creator::is_available($this->course, 'lti'), 'No tools yet.');

        $this->setAdminUser();
        $typeid = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Publisher exercises', 'baseurl' => 'https://example.com/lti',
            'coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER, 'state' => LTI_TOOL_STATE_CONFIGURED,
        ]);
        $this->setUser($this->teacher);
        $_POST['sesskey'] = sesskey();
        $this->assertTrue(\tool_wizards\local\module_creator::is_available($this->course, 'lti'));

        $form = dynamic_form_ws::execute(wizard_form::class, 'wizard=externaltool&courseid=' . $this->course->id);
        $this->assertStringContainsString('Publisher exercises', $form['html']);

        $result = $this->submit('externaltool', ['typeid' => $typeid, 'name' => 'Week 3 exercises']);
        $this->assertTrue($result['submitted'], strip_tags($result['html'] ?? ''));
        $lti = $DB->get_record('lti', ['course' => $this->course->id, 'name' => 'Week 3 exercises'], '*', MUST_EXIST);
        $this->assertEquals($typeid, $lti->typeid);
    }

    /**
     * Submitting the quiz wizard with a true/false question creates both.
     */
    public function test_submit_quiz_with_question(): void {
        global $DB;
        $result = $this->submit('quiz', ['name' => 'Quick check', 'purpose' => 'practice', 'firstquestion' => 'truefalse',
            'questiontext' => 'Water boils at 100 degrees Celsius at sea level.', 'truefalse' => 1]);
        $this->assertTrue($result['submitted'], strip_tags($result['html'] ?? ''));
        $quiz = $DB->get_record('quiz', ['course' => $this->course->id, 'name' => 'Quick check'], '*', MUST_EXIST);
        $this->assertEquals(1, $DB->count_records('quiz_slots', ['quizid' => $quiz->id]));
    }

    /**
     * A first question without its text is refused before anything is created.
     */
    public function test_quiz_question_needs_text(): void {
        global $DB;
        $result = $this->submit('quiz', ['name' => 'Nope', 'purpose' => 'practice', 'firstquestion' => 'multichoice',
            'questiontext' => '', 'choice1' => 'A', 'choice2' => 'B', 'correctchoice' => 1]);
        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('error_questiontext', 'tool_wizards'), $result['html']);
        $this->assertFalse($DB->record_exists('quiz', ['course' => $this->course->id]));
    }

    /**
     * "Try it" creates nothing and says what would be set; only wizard managers may use it.
     */
    public function test_preview(): void {
        global $DB;
        $values = ['name' => 'Just looking', 'description' => '', 'purpose' => 'qanda', 'preview' => 1];
        try {
            $this->submit('forum', $values);
            $this->fail('A teacher cannot use "Try it".');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_string('wizards:managewizards', 'tool_wizards'), $e->getMessage());
        }
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        $result = $this->submit('forum', $values);
        $this->assertTrue($result['submitted'], strip_tags($result['html'] ?? ''));
        $data = json_decode($result['data'], true);
        $this->assertTrue($data['preview']);
        $this->assertContains('type = qanda', $data['lines']);
        $this->assertFalse($DB->record_exists('forum', ['course' => $this->course->id, 'name' => 'Just looking']));
    }

    /**
     * A disabled wizard cannot be used.
     */
    public function test_disabled_wizard_refused(): void {
        repository::set_status((int) repository::get_by_key('forum')->id, repository::STATUS_DISABLED);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_nowizard', 'tool_wizards'));
        dynamic_form_ws::execute(wizard_form::class, 'wizard=forum&courseid=' . $this->course->id);
    }

    /**
     * A student cannot open a wizard, whatever course id the browser sends.
     */
    public function test_student_refused(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $this->expectException(\required_capability_exception::class);
        dynamic_form_ws::execute(wizard_form::class, 'wizard=forum&courseid=' . $this->course->id);
    }

    /**
     * A teacher cannot open a wizard for another course (the id comes from the browser).
     */
    public function test_other_course_refused(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->expectException(\require_login_exception::class);
        dynamic_form_ws::execute(wizard_form::class, 'wizard=forum&courseid=' . $other->id);
    }

    /**
     * A module the teacher may not add cannot be added through its wizard either.
     */
    public function test_prohibited_module_refused(): void {
        $roleid = create_role('No forums', 'noforums', '');
        assign_capability('mod/forum:addinstance', CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $this->teacher->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_notavailable', 'tool_wizards'));
        dynamic_form_ws::execute(wizard_form::class, 'wizard=forum&courseid=' . $this->course->id);
    }
}
