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
use mod_quiz\question\display_options;

/**
 * The purpose presets: each purpose, accepted with "Enough questions, let's go" straight after
 * the first screen, creates the activity with the settings that suit it.
 *
 * "Let's go" posts what the later screens hold, which is the chosen purpose's preset (the stepper
 * fills them in when the purpose is picked), so each test posts the essentials plus the preset.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(add_module_base::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(add_quiz::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(add_forum::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(add_glossary::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(add_file::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(add_slides::class)]
final class presets_test extends advanced_testcase {
    /** @var \stdClass the course, with completion tracking on */
    protected \stdClass $course;

    /**
     * A course with completion tracking, and its teacher.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2, 'enablecompletion' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $_POST['sesskey'] = sesskey();
    }

    /**
     * Submit a mini-wizard with the essentials and a purpose's preset, as "let's go" does.
     *
     * @param string $class the form class
     * @param string $purpose the purpose
     * @param array $extra more answers (overriding the preset)
     * @return array the web service result
     */
    protected function letsgo(string $class, string $purpose, array $extra = []): array {
        $shortclass = substr($class, strrpos($class, '\\') + 1);
        $values = array_replace(
            ['courseid' => $this->course->id, 'name' => 'Wizard ' . $purpose, 'description' => 'What it is for.',
                'purpose' => $purpose, 'sesskey' => sesskey(), '_qf__tool_wizards_form_' . $shortclass => 1],
            $class::presets()[$purpose] ?? [],
            $extra
        );
        return dynamic_form_ws::execute($class, http_build_query($values));
    }

    /**
     * The course module and instance the last submission created.
     *
     * @param array $result the web service result
     * @param string $table the module table
     * @return array [\stdClass $cm, \stdClass $instance]
     */
    protected function created(array $result, string $table): array {
        global $DB;
        $this->assertTrue($result['submitted'], strip_tags($result['html'] ?? ''));
        $cmid = json_decode($result['data'])->cmid;
        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        return [$cm, $DB->get_record($table, ['id' => $cm->instance], '*', MUST_EXIST)];
    }

    /**
     * Whether a quiz review option is on at a time.
     *
     * @param \stdClass $quiz the quiz
     * @param string $row review field, e.g. rightanswer
     * @param int $when a display_options time constant
     * @return bool
     */
    protected static function shown(\stdClass $quiz, string $row, int $when): bool {
        return (bool) ($quiz->{'review' . $row} & $when);
    }

    /**
     * Practice: interactive, unlimited tries, everything shown at once, done when attempted.
     */
    public function test_quiz_practice(): void {
        [$cm, $quiz] = $this->created($this->letsgo(add_quiz::class, 'practice'), 'quiz');
        $this->assertSame('interactive', $quiz->preferredbehaviour);
        $this->assertEquals(0, $quiz->attempts);
        $this->assertTrue(self::shown($quiz, 'rightanswer', display_options::IMMEDIATELY_AFTER));
        $this->assertTrue(self::shown($quiz, 'correctness', display_options::DURING), 'Interactive shows feedback during.');
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, $cm->completion);
        $this->assertEquals(1, $quiz->completionminattempts);
    }

    /**
     * Graded test: deferred feedback, one try, only the score before it closes.
     */
    public function test_quiz_exam(): void {
        [$cm, $quiz] = $this->created($this->letsgo(add_quiz::class, 'exam'), 'quiz');
        $this->assertSame('deferredfeedback', $quiz->preferredbehaviour);
        $this->assertEquals(1, $quiz->attempts);
        $this->assertTrue(self::shown($quiz, 'marks', display_options::IMMEDIATELY_AFTER));
        foreach (['correctness', 'rightanswer', 'specificfeedback'] as $row) {
            $this->assertFalse(self::shown($quiz, $row, display_options::IMMEDIATELY_AFTER), $row);
            $this->assertFalse(self::shown($quiz, $row, display_options::LATER_WHILE_OPEN), $row);
        }
        $this->assertEquals(0, $cm->completiongradeitemnumber, 'Done when graded.');
        $this->assertEquals(0, $cm->completionpassgrade);
    }

    /**
     * Pre-test: the right answers are never shown, even after a closing date.
     */
    public function test_quiz_pretest_never_shows_answers(): void {
        $close = time() + WEEKSECS;
        $date = usergetdate($close);
        [, $quiz] = $this->created($this->letsgo(add_quiz::class, 'pretest', ['timeclose' => [
            'enabled' => 1, 'day' => $date['mday'], 'month' => $date['mon'], 'year' => $date['year'],
            'hour' => $date['hours'], 'minute' => $date['minutes'],
        ]]), 'quiz');
        $this->assertEquals(1, $quiz->attempts);
        $this->assertEquals(0, $quiz->questionsperpage, 'All on one page.');
        $this->assertGreaterThan(0, $quiz->timeclose);
        foreach ([display_options::IMMEDIATELY_AFTER, display_options::LATER_WHILE_OPEN, display_options::AFTER_CLOSE] as $when) {
            $this->assertFalse(self::shown($quiz, 'rightanswer', $when), "rightanswer at $when");
            $this->assertTrue(self::shown($quiz, 'correctness', $when), "correctness at $when");
        }
    }

    /**
     * Homework: three tries, best counts, correct answers held back until it closes.
     */
    public function test_quiz_homework(): void {
        [, $quiz] = $this->created($this->letsgo(add_quiz::class, 'homework'), 'quiz');
        $this->assertEquals(3, $quiz->attempts);
        $this->assertEquals(QUIZ_GRADEHIGHEST, $quiz->grademethod);
        $this->assertTrue(self::shown($quiz, 'specificfeedback', display_options::IMMEDIATELY_AFTER));
        $this->assertFalse(self::shown($quiz, 'rightanswer', display_options::IMMEDIATELY_AFTER));
    }

    /**
     * A pass mark as a percentage becomes the quiz's grade to pass, and "done when passed" needs one.
     */
    public function test_quiz_pass_mark(): void {
        $result = $this->letsgo(add_quiz::class, 'exam', ['completionchoice' => 'passed']);
        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('error_passneeded', 'tool_wizards'), $result['html']);

        [$cm] = $this->created($this->letsgo(
            add_quiz::class,
            'exam',
            ['haspass' => 1, 'passpercent' => 60, 'completionchoice' => 'passed']
        ), 'quiz');
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $cm->instance,
            'courseid' => $this->course->id]);
        $this->assertEquals(0.6 * get_config('quiz', 'maximumgrade'), (float) $item->gradepass);
        $this->assertEquals(1, $cm->completionpassgrade);
    }

    /**
     * Each forum purpose makes its kind of forum, with its email and completion settings.
     *
     * @param string $purpose the purpose
     * @param string $type the forum type
     * @param int $subscribe the subscription mode
     * @param string $rule the completion field that must be 1 ('' for the course default)
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('forum_provider')]
    public function test_forum(string $purpose, string $type, int $subscribe, string $rule): void {
        [$cm, $forum] = $this->created($this->letsgo(add_forum::class, $purpose), 'forum');
        $this->assertSame($type, $forum->type);
        $this->assertEquals($subscribe, $forum->forcesubscribe);
        $this->assertEquals(0, $forum->assessed);
        foreach (['completionposts', 'completiondiscussions', 'completionreplies'] as $field) {
            $this->assertEquals($field === $rule ? 1 : 0, $forum->$field, $field);
        }
        if ($rule !== '') {
            $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, $cm->completion);
        }
    }

    /**
     * Forum purposes.
     *
     * @return array
     */
    public static function forum_provider(): array {
        return [
            'general' => ['general', 'general', 2, 'completionposts'],
            'questions' => ['questions', 'general', 0, ''],
            'qanda' => ['qanda', 'qanda', 0, 'completionreplies'],
            'single' => ['single', 'single', 2, 'completionreplies'],
            'eachuser' => ['eachuser', 'eachuser', 0, 'completiondiscussions'],
            'blog' => ['blog', 'blog', 0, 'completiondiscussions'],
        ];
    }

    /**
     * Whole-forum grading and rated posts reach the forum's own grade settings.
     */
    public function test_forum_grading(): void {
        [, $forum] = $this->created(
            $this->letsgo(add_forum::class, 'general', ['gradingchoice' => 'whole', 'maxgrade' => 20]),
            'forum'
        );
        $this->assertEquals(20, $forum->grade_forum);
        $this->assertEquals(0, $forum->assessed);
        [, $forum] = $this->created(
            $this->letsgo(add_forum::class, 'general', ['gradingchoice' => 'ratings', 'maxgrade' => 5]),
            'forum'
        );
        $this->assertEquals(RATING_AGGREGATE_AVERAGE, $forum->assessed);
        $this->assertEquals(5, $forum->scale);
    }

    /**
     * A single discussion needs its opening post, and core's own checks show on the answer they concern.
     */
    public function test_forum_errors_placed(): void {
        global $DB;
        $result = $this->letsgo(add_forum::class, 'single', ['description' => '']);
        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('error_singledescription', 'tool_wizards'), $result['html']);

        // Last day for posts before the due date: refused by mod_forum's own validation.
        $due = usergetdate(time() + 2 * DAYSECS);
        $cutoff = usergetdate(time() + DAYSECS);
        $date = fn($d) => ['enabled' => 1, 'day' => $d['mday'], 'month' => $d['mon'], 'year' => $d['year'],
            'hour' => $d['hours'], 'minute' => $d['minutes']];
        $result = $this->letsgo(add_forum::class, 'general', ['duedate' => $date($due), 'cutoffdate' => $date($cutoff)]);
        $this->assertFalse($result['submitted']);
        $message = get_string('cutoffdatevalidation', 'forum');
        $this->assertMatchesRegularExpression(
            '/id="id_error_cutoffdate(_\w+)?"[^>]*>\s*' . preg_quote($message, '/') . '/',
            $result['html'],
            'The error is shown on the last-day field.'
        );
        $html = $result['html'];
        $start = strpos($html, 'data-step="deadline"');
        $next = strpos($html, 'data-step=', $start + 1);
        $at = strpos($html, $message);
        $this->assertTrue(
            $start !== false && $at > $start && ($next === false || $at < $next),
            'That field is on the Deadline screen, which the stepper opens.'
        );
        $this->assertFalse($DB->record_exists('forum', ['course' => $this->course->id, 'type' => 'general',
            'name' => 'Wizard general']));
    }

    /**
     * Each glossary purpose sets approval, look and completion.
     *
     * @param string $purpose the purpose
     * @param int $approval defaultapproval
     * @param string $format displayformat
     * @param int $entries completionentries (0 for none)
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('glossary_provider')]
    public function test_glossary(string $purpose, int $approval, string $format, int $entries): void {
        [, $glossary] = $this->created($this->letsgo(add_glossary::class, $purpose), 'glossary');
        $this->assertEquals($approval, $glossary->defaultapproval);
        $this->assertSame($format, $glossary->displayformat);
        $this->assertEquals($entries, $glossary->completionentries);
    }

    /**
     * Glossary purposes.
     *
     * @return array
     */
    public static function glossary_provider(): array {
        return [
            'vocabulary' => ['vocabulary', 0, 'dictionary', 0],
            'shared' => ['shared', 1, 'fullwithauthor', 3],
            'faq' => ['faq', 0, 'faq', 0],
            'links' => ['links', 1, 'encyclopedia', 1],
        ];
    }

    /**
     * A draft area holding one file.
     *
     * @param string $filename the file name
     * @return int the draft item id
     */
    protected function draft_file(string $filename): int {
        global $USER;
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftitemid,
            'filepath' => '/', 'filename' => $filename,
        ], 'content');
        return $draftitemid;
    }

    /**
     * What students do with a file decides how it opens.
     *
     * @param string $class the form class
     * @param string $purpose the purpose
     * @param string $filename the uploaded file
     * @param int $display the resourcelib display
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('file_provider')]
    public function test_file_display(string $class, string $purpose, string $filename, int $display): void {
        [, $resource] = $this->created($this->letsgo($class, $purpose, ['files' => $this->draft_file($filename)]), 'resource');
        $this->assertEquals($display, $resource->display);
    }

    /**
     * File and slides purposes.
     *
     * @return array
     */
    public static function file_provider(): array {
        return [
            'read' => [add_file::class, 'read', 'notes.pdf', 1],
            'download' => [add_file::class, 'download', 'worksheet.docx', 4],
            'print' => [add_file::class, 'print', 'handout.pdf', 5],
            'slides view' => [add_slides::class, 'view', 'week1.pdf', 1],
            'slides download' => [add_slides::class, 'download', 'week1.pptx', 4],
        ];
    }

    /**
     * Slides to look through in the course must be a PDF.
     */
    public function test_slides_view_needs_pdf(): void {
        $result = $this->letsgo(add_slides::class, 'view', ['files' => $this->draft_file('week1.pptx')]);
        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('error_slidesnotpdf', 'tool_wizards'), $result['html']);
    }

    /**
     * The groups screen appears only where groups can matter, and visibility and group mode are stored.
     */
    public function test_shared_screens(): void {
        $load = fn() => dynamic_form_ws::execute(add_forum::class, 'courseid=' . $this->course->id)['html'];
        $this->assertStringNotContainsString('data-step="groups"', $load(), 'No groups in the course yet.');
        $this->assertStringContainsString('data-step="completion"', $load());
        $this->assertStringContainsString('data-step="visibility"', $load());

        $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->assertStringContainsString('data-step="groups"', $load());

        $result = $this->letsgo(add_forum::class, 'general', ['groupmode' => SEPARATEGROUPS, 'visible' => 0]);
        [$cm] = $this->created($result, 'forum');
        $this->assertEquals(SEPARATEGROUPS, $cm->groupmode);
        $this->assertEquals(0, $cm->visible);

        global $COURSE, $DB;
        update_course((object) ['id' => $this->course->id, 'groupmodeforce' => 1, 'groupmode' => VISIBLEGROUPS]);
        // A new request would load the course afresh; get_course() here would return the earlier $COURSE.
        $COURSE = $DB->get_record('course', ['id' => $this->course->id], '*', MUST_EXIST);
        $this->assertStringNotContainsString('data-step="groups"', $load(), 'Not when the course forces a group mode.');
    }

    /**
     * Without a purpose there is nothing to base the settings on.
     */
    public function test_purpose_required(): void {
        $result = $this->letsgo(add_forum::class, 'nonsense');
        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('error_purpose', 'tool_wizards'), $result['html']);
    }
}
