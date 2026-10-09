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
    const DEFAULTS = ['file', 'slides', 'picture', 'page', 'forum', 'glossary', 'quiz'];

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
        foreach (self::DEFAULTS as $key) {
            $result = dynamic_form_ws::execute(wizard_form::class, 'wizard=' . $key . '&courseid=' . $this->course->id);
            $this->assertFalse($result['submitted'], $key);
            $this->assertStringContainsString('data-step="basics"', $result['html'], $key);
            $this->assertStringContainsString('tool_wizards/modal_stepper', $result['javascript'], $key);
            $this->assertStringNotContainsString('[[', $result['html'], "$key: no missing strings");
        }
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
