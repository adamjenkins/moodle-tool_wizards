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
use tool_wizards\local\module_types;

/**
 * The mini-wizard forms, driven through core's dynamic form web service as the modal drives them.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(add_module_base::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(add_quiz::class)]
final class mini_wizards_test extends advanced_testcase {
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
     * Every mini-wizard's form loads for the teacher.
     */
    public function test_every_form_loads(): void {
        foreach (module_types::all() as $type) {
            $result = dynamic_form_ws::execute(module_types::formclass($type), 'courseid=' . $this->course->id);
            $this->assertFalse($result['submitted'], $type);
            $this->assertStringContainsString('name="courseid"', $result['html'], $type);
        }
    }

    /**
     * Submitting the forum mini-wizard creates the forum.
     */
    public function test_submit_forum(): void {
        global $DB;
        $formdata = http_build_query([
            'courseid' => $this->course->id,
            'name' => 'Week 1 discussion',
            'description' => 'Say hello',
            'sesskey' => sesskey(),
            '_qf__tool_wizards_form_add_forum' => 1,
        ]);
        $result = dynamic_form_ws::execute(add_forum::class, $formdata);
        $this->assertTrue($result['submitted']);
        $data = json_decode($result['data']);
        $this->assertSame('Week 1 discussion', $data->name);
        $this->assertTrue($DB->record_exists('forum', ['course' => $this->course->id, 'name' => 'Week 1 discussion']));
    }

    /**
     * Submitting the quiz mini-wizard with a true/false question creates both.
     */
    public function test_submit_quiz_with_question(): void {
        global $DB;
        $formdata = http_build_query([
            'courseid' => $this->course->id,
            'name' => 'Quick check',
            'firstquestion' => 'truefalse',
            'questiontext' => 'Water boils at 100 degrees Celsius at sea level.',
            'truefalse' => 1,
            'correctchoice' => 1,
            'sesskey' => sesskey(),
            '_qf__tool_wizards_form_add_quiz' => 1,
        ]);
        $result = dynamic_form_ws::execute(add_quiz::class, $formdata);
        $this->assertTrue($result['submitted'], $result['html'] ?? '');
        $quiz = $DB->get_record('quiz', ['course' => $this->course->id, 'name' => 'Quick check'], '*', MUST_EXIST);
        $this->assertEquals(1, $DB->count_records('quiz_slots', ['quizid' => $quiz->id]));
    }

    /**
     * A first question without its text is refused before anything is created.
     */
    public function test_quiz_question_needs_text(): void {
        global $DB;
        $formdata = http_build_query([
            'courseid' => $this->course->id,
            'name' => 'Nope',
            'firstquestion' => 'multichoice',
            'questiontext' => '',
            'choice1' => 'A',
            'choice2' => 'B',
            'correctchoice' => 1,
            'sesskey' => sesskey(),
            '_qf__tool_wizards_form_add_quiz' => 1,
        ]);
        $result = dynamic_form_ws::execute(add_quiz::class, $formdata);
        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('error_questiontext', 'tool_wizards'), $result['html']);
        $this->assertFalse($DB->record_exists('quiz', ['course' => $this->course->id]));
    }

    /**
     * A student cannot open a mini-wizard, whatever course id the browser sends.
     */
    public function test_student_refused(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $this->expectException(\required_capability_exception::class);
        dynamic_form_ws::execute(add_forum::class, 'courseid=' . $this->course->id);
    }

    /**
     * A teacher cannot open a mini-wizard for another course (the id comes from the browser).
     */
    public function test_other_course_refused(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->expectException(\require_login_exception::class);
        dynamic_form_ws::execute(add_forum::class, 'courseid=' . $other->id);
    }

    /**
     * A module the teacher may not add cannot be added through its mini-wizard either.
     */
    public function test_prohibited_module_refused(): void {
        $roleid = create_role('No forums', 'noforums', '');
        assign_capability('mod/forum:addinstance', CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $this->teacher->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_notavailable', 'tool_wizards'));
        dynamic_form_ws::execute(add_forum::class, 'courseid=' . $this->course->id);
    }
}
