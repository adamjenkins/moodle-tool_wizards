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

/**
 * Tests for adding first content through the mini-wizards' creator.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(module_creator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(module_types::class)]
final class module_creator_test extends advanced_testcase {
    /** @var \stdClass the course */
    protected \stdClass $course;

    /** @var \stdClass an editing teacher in the course */
    protected \stdClass $teacher;

    /**
     * A course with sections, and a real editing teacher (admins would bypass the checks under test).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 3, 'enablecompletion' => 1]);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);
    }

    /**
     * A draft area of the current user holding one file.
     *
     * @param string $filename the file name
     * @param string $content the content
     * @return int draft item id
     */
    protected function draft_with_file(string $filename, string $content = 'hello'): int {
        global $USER;
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftitemid;
    }

    /**
     * A forum from a name and a plain description.
     */
    public function test_forum(): void {
        global $DB;
        $sink = $this->redirectEvents();
        $cm = module_creator::create($this->course, 'forum', [
            'name' => 'Week 1 discussion',
            'introeditor' => ['text' => '<p>Say hello</p>', 'format' => FORMAT_HTML],
        ]);
        $events = array_map(fn($e) => get_class($e), $sink->get_events());
        $sink->close();

        $this->assertSame('forum', $cm->modname);
        $this->assertEquals(1, $cm->sectionnum, 'Content goes into the first section after General.');
        $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Week 1 discussion', $forum->name);
        $this->assertSame('general', $forum->type);
        $this->assertSame('<p>Say hello</p>', $forum->intro);
        $this->assertContains(\core\event\course_module_created::class, $events);
        $this->assertDebuggingNotCalled();
    }

    /**
     * A file, stored as the resource's main file.
     */
    public function test_file(): void {
        global $DB;
        $draft = $this->draft_with_file('handout.pdf', '%PDF-1.4 test');
        $cm = module_creator::create($this->course, 'file', ['name' => 'Week 1 handout', 'files' => $draft]);

        $this->assertSame('resource', $cm->modname);
        $resource = $DB->get_record('resource', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Week 1 handout', $resource->name);
        $this->assertEquals(get_config('resource', 'display'), $resource->display, 'The site default display.');
        $files = get_file_storage()->get_area_files($cm->context->id, 'mod_resource', 'content', 0, 'sortorder DESC', false);
        $this->assertCount(1, $files);
        $main = reset($files);
        $this->assertSame('handout.pdf', $main->get_filename());
        $this->assertEquals(1, $main->get_sortorder(), 'The only file is the main file.');
        $this->assertDebuggingNotCalled();
    }

    /**
     * The resource form refuses an empty upload, as the standard form does.
     */
    public function test_file_needs_a_file(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_moduleform', 'tool_wizards'));
        module_creator::create($this->course, 'file', ['name' => 'Nothing', 'files' => file_get_unused_draft_itemid()]);
    }

    /**
     * Slides are a resource too.
     */
    public function test_slides(): void {
        $draft = $this->draft_with_file('lecture.pptx');
        $cm = module_creator::create($this->course, 'slides', ['name' => 'Lecture 1', 'files' => $draft]);
        $this->assertSame('resource', $cm->modname);
        $this->assertSame('Lecture 1', $cm->name);
    }

    /**
     * A page keeps its content, and an image in it is saved with the page.
     */
    public function test_page_with_image(): void {
        global $DB;
        $draft = $this->draft_with_file('photo.png', 'not really a png');
        $text = '<p>Welcome!</p><p><img src="' . \core\url::make_draftfile_url($draft, '/', 'photo.png')->out(false)
            . '" alt="Our classroom"></p>';
        $cm = module_creator::create($this->course, 'page', [
            'name' => 'Welcome',
            'page' => ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => $draft],
        ]);

        $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Welcome', $page->name);
        $this->assertStringContainsString('<p>Welcome!</p>', $page->content);
        $this->assertStringContainsString('@@PLUGINFILE@@/photo.png', $page->content);
        $files = get_file_storage()->get_area_files($cm->context->id, 'mod_page', 'content', 0, 'id', false);
        $this->assertSame(['photo.png'], array_values(array_map(fn($f) => $f->get_filename(), $files)));
        $this->assertDebuggingNotCalled();
    }

    /**
     * A module the teacher may not add is neither offered nor created, even though the
     * course-level teacher role allows it: a prohibition in a role held at system level wins,
     * which is how tool_teacherscaffold restricts new teachers.
     */
    public function test_prohibited_module_not_offered(): void {
        global $DB;
        $this->assertContains('forum', module_types::available($this->course));

        $roleid = create_role('Locked forum', 'lockedforum', 'Prohibits adding forums');
        assign_capability('mod/forum:addinstance', CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $this->teacher->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('mod/forum:addinstance', \context_course::instance($this->course->id)));

        $this->assertNotContains('forum', module_types::available($this->course));
        $this->assertContains('page', module_types::available($this->course));
        $sectionsbefore = $DB->count_records('course_sections', ['course' => $this->course->id]);
        try {
            module_creator::create($this->course, 'forum', ['name' => 'Not allowed'], 7);
            $this->fail('A prohibited module was created.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_notavailable', $e->errorcode);
        }
        $this->assertSame(
            $sectionsbefore,
            $DB->count_records('course_sections', ['course' => $this->course->id]),
            'Refusing does not leave a new section behind.'
        );
    }

    /**
     * A disabled module is not offered.
     */
    public function test_disabled_module_not_offered(): void {
        $this->assertContains('page', module_types::available($this->course));
        \core\plugininfo\mod::enable_plugin('page', 0);
        $this->assertNotContains('page', module_types::available($this->course));
    }

    /**
     * A glossary with the site's own glossary defaults.
     */
    public function test_glossary(): void {
        global $DB;
        $cm = module_creator::create($this->course, 'glossary', [
            'name' => 'Key terms',
            'introeditor' => ['text' => '<p>Words for this unit</p>', 'format' => FORMAT_HTML],
        ]);
        $glossary = $DB->get_record('glossary', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Key terms', $glossary->name);
        $this->assertSame('dictionary', $glossary->displayformat);
        $this->assertEquals(0, $glossary->mainglossary);
        $this->assertDebuggingNotCalled();
    }

    /**
     * A picture on the course page: a text and media area holding the image, with its alt text.
     */
    public function test_picture(): void {
        global $DB, $CFG;
        $draft = $this->draft_with_file('class.png', file_get_contents($CFG->dirroot . '/lib/tests/fixtures/gd-logo.png'));
        $form = new \ReflectionMethod(\tool_wizards\form\add_picture::class, 'answers_to_fields');
        $fields = $form->invoke(
            (new \ReflectionClass(\tool_wizards\form\add_picture::class))->newInstanceWithoutConstructor(),
            (object) ['picture' => $draft, 'alt' => 'Our class <on> the trip', 'caption' => 'Museum visit']
        );

        $cm = module_creator::create($this->course, 'picture', $fields);
        $label = $DB->get_record('label', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Museum visit', $label->name);
        $this->assertStringContainsString('src="@@PLUGINFILE@@/class.png"', $label->intro);
        $this->assertStringContainsString('alt="Our class &lt;on&gt; the trip"', $label->intro, 'Escaped at the sink.');
        $this->assertStringContainsString('<figcaption>Museum visit</figcaption>', $label->intro);
        $files = get_file_storage()->get_area_files($cm->context->id, 'mod_label', 'intro', 0, 'id', false);
        $this->assertSame(['class.png'], array_values(array_map(fn($f) => $f->get_filename(), $files)));
        $this->assertDebuggingNotCalled();
    }

    /**
     * A quiz with its module defaults, then a first multiple-choice question added to it.
     */
    public function test_quiz_with_multichoice_question(): void {
        global $DB;
        $cm = module_creator::create($this->course, 'quiz', ['name' => 'Check your understanding']);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Check your understanding', $quiz->name);
        $this->assertSame(get_config('quiz', 'preferredbehaviour'), $quiz->preferredbehaviour);

        $fields = \tool_wizards\form\add_quiz::question_fields('multichoice', (object) [
            'questiontext' => 'Which planet is largest?',
            'choice1' => 'Mars', 'choice2' => 'Jupiter', 'choice3' => '', 'choice4' => 'Venus',
            'correctchoice' => 2,
        ]);
        $question = question_creator::add_to_quiz($cm, 'multichoice', $fields);

        $this->assertSame('multichoice', $question->qtype);
        $answers = $DB->get_records('question_answers', ['question' => $question->id], 'id');
        $this->assertSame(
            ['Mars' => '0.0000000', 'Jupiter' => '1.0000000', 'Venus' => '0.0000000'],
            array_column(array_values($answers), 'fraction', 'answer')
        );
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
        $this->assertCount(1, $quizobj->get_structure()->get_slots());
        $this->assertEquals(1, $DB->get_field('quiz', 'sumgrades', ['id' => $quiz->id]), 'Total marks recomputed.');
        $this->assertDebuggingNotCalled();
    }

    /**
     * A true or false first question.
     */
    public function test_quiz_with_truefalse_question(): void {
        global $DB;
        $cm = module_creator::create($this->course, 'quiz', ['name' => 'Quick check']);
        $question = question_creator::add_to_quiz($cm, 'truefalse', \tool_wizards\form\add_quiz::question_fields(
            'truefalse',
            (object) ['questiontext' => 'The sun is a star.', 'truefalse' => 1]
        ));
        $options = $DB->get_record('question_truefalse', ['question' => $question->id], '*', MUST_EXIST);
        $this->assertSame('1.0000000', $DB->get_field('question_answers', 'fraction', ['id' => $options->trueanswer]));
        $this->assertSame('0.0000000', $DB->get_field('question_answers', 'fraction', ['id' => $options->falseanswer]));
        $this->assertCount(1, \mod_quiz\quiz_settings::create($cm->instance)->get_structure()->get_slots());
    }

    /**
     * A student is offered nothing.
     */
    public function test_student_offered_nothing(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $this->assertSame([], module_types::available($this->course));
    }
}
