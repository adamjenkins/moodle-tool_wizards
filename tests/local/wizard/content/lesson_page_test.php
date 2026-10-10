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

namespace tool_wizards\local\wizard\content;

use tool_wizards\local\content_creator;
use tool_wizards\local\wizard\defaults;
use tool_wizards\local\wizard\engine;

/**
 * Tests for the lesson page wizard: every kind of page is saved through the lesson's own API.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(lesson_page::class)]
final class lesson_page_test extends \advanced_testcase {
    /**
     * Load the lesson library for its constants.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/lesson/locallib.php');
    }

    /**
     * A course with a teacher (logged in) and a lesson.
     *
     * @param array $settings lesson settings
     * @return \cm_info the lesson
     */
    protected function make_lesson(array $settings = []): \cm_info {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->setUser($generator->create_and_enrol($course, 'editingteacher'));
        $lesson = $generator->create_module('lesson', $settings + ['course' => $course->id, 'custom' => 1, 'maxanswers' => 4]);
        return get_fast_modinfo($course)->get_cm($lesson->cmid);
    }

    /**
     * The shipped definition.
     *
     * @return array
     */
    protected function doc(): array {
        return defaults::shipped()['lessonpage'];
    }

    /**
     * The lesson's pages in order.
     *
     * @param \cm_info $cm the lesson
     * @return \stdClass[] page records
     */
    protected function pages(\cm_info $cm): array {
        global $DB;
        $pages = $DB->get_records('lesson_pages', ['lessonid' => $cm->instance]);
        $out = [];
        $id = $DB->get_field('lesson_pages', 'id', ['lessonid' => $cm->instance, 'prevpageid' => 0]);
        while ($id) {
            $out[] = $pages[$id];
            $id = (int) $pages[$id]->nextpageid;
        }
        $this->assertCount(count($pages), $out, 'The pages form one chain.');
        return $out;
    }

    /**
     * The answers of a page, in the order they were saved.
     *
     * @param int $pageid the page
     * @return \stdClass[]
     */
    protected function answers(int $pageid): array {
        global $DB;
        return array_values($DB->get_records('lesson_answers', ['pageid' => $pageid], 'id'));
    }

    /**
     * Each kind of page is added at the end, with its answers, jumps and scores.
     */
    public function test_save_each_kind(): void {
        $this->resetAfterTest();
        $cm = $this->make_lesson();
        $doc = $this->doc();
        $q = fn(string $text) => ['text' => "<p>$text</p>", 'format' => FORMAT_HTML];

        $added = content_creator::save($doc, $cm, ['kind' => 'content', 'title' => 'Welcome',
            'contents' => $q('Read this first.'), 'button' => [1 => 'Go on', 2 => 'Finish'],
            'goto' => [1 => 'next', 2 => 'end']]);
        $this->assertSame('Page 1: Welcome', $added);

        content_creator::save($doc, $cm, ['kind' => 'multichoice', 'title' => 'Capital', 'question' => $q('Capital of France?'),
            'choice' => [1 => 'Lyon', 2 => 'Paris', 3 => 'Nice'], 'correct' => '2',
            'rightjump' => 'next', 'wrongjump' => 'previous',
            'rightfeedback' => $q('Yes.'), 'wrongfeedback' => $q('No.')]);
        content_creator::save($doc, $cm, ['kind' => 'truefalse', 'title' => 'Sky', 'question' => $q('The sky is green.'),
            'truefalse' => '0']);
        content_creator::save($doc, $cm, ['kind' => 'shortanswer', 'title' => 'Colour', 'question' => $q('Name a colour.'),
            'accept' => [1 => 'red', 3 => 'blue', 2 => ''], 'wrongjump' => 'this']);
        content_creator::save($doc, $cm, ['kind' => 'numerical', 'title' => 'Sum', 'question' => $q('5 + 5?'),
            'number' => '10', 'margin' => '0.5']);
        content_creator::save($doc, $cm, ['kind' => 'matching', 'title' => 'Pairs', 'question' => $q('Match them.'),
            'item' => [1 => 'Cat', 2 => 'Dog'], 'match' => [1 => 'Meow', 2 => 'Woof']]);
        content_creator::save($doc, $cm, ['kind' => 'essay', 'title' => 'Essay', 'question' => $q('Write.'), 'afterjump' => 'end']);
        $added = content_creator::save($doc, $cm, ['kind' => 'endofbranch']);
        $this->assertSame('Page 8: ' . get_string('endofbranch', 'lesson'), $added);

        $pages = $this->pages($cm);
        $this->assertSame(
            ['Welcome', 'Capital', 'Sky', 'Colour', 'Sum', 'Pairs', 'Essay', get_string('endofbranch', 'lesson')],
            array_column($pages, 'title')
        );
        $this->assertEquals(
            [LESSON_PAGE_BRANCHTABLE, LESSON_PAGE_MULTICHOICE, LESSON_PAGE_TRUEFALSE, LESSON_PAGE_SHORTANSWER,
            LESSON_PAGE_NUMERICAL, LESSON_PAGE_MATCHING, LESSON_PAGE_ESSAY, LESSON_PAGE_ENDOFBRANCH],
            array_column($pages, 'qtype')
        );

        // Content page: two buttons, ticked layout and menu as core's form does.
        $this->assertEquals(1, $pages[0]->layout);
        $this->assertEquals(1, $pages[0]->display);
        $answers = $this->answers($pages[0]->id);
        $this->assertSame(['Go on', 'Finish'], array_column($answers, 'answer'));
        $this->assertEquals([LESSON_NEXTPAGE, LESSON_EOL], array_column($answers, 'jumpto'));

        // Multiple choice: the second answer is the right one.
        $answers = $this->answers($pages[1]->id);
        $this->assertSame(['Lyon', 'Paris', 'Nice'], array_column($answers, 'answer'));
        $this->assertEquals([0, 1, 0], array_column($answers, 'score'));
        $this->assertEquals([LESSON_PREVIOUSPAGE, LESSON_NEXTPAGE, LESSON_PREVIOUSPAGE], array_column($answers, 'jumpto'));
        $this->assertSame('<p>Yes.</p>', $answers[1]->response);

        // True/false: the statement is false, so "False" is the right answer (core's first answer).
        $answers = $this->answers($pages[2]->id);
        $this->assertSame(get_string('lessonpage_false', 'tool_wizards'), $answers[0]->answer);
        $this->assertEquals([LESSON_NEXTPAGE, LESSON_THISPAGE], array_column($answers, 'jumpto'));

        // Short answer: the two answers in order, then "all other answers".
        $answers = $this->answers($pages[3]->id);
        $this->assertSame(['red', 'blue', LESSON_OTHER_ANSWERS], array_column($answers, 'answer'));
        $this->assertEquals([1, 1, 0], array_column($answers, 'score'));
        $this->assertEquals([LESSON_NEXTPAGE, LESSON_NEXTPAGE, LESSON_THISPAGE], array_column($answers, 'jumpto'));

        // Number: a range.
        $answers = $this->answers($pages[4]->id);
        $this->assertSame(['9.5:10.5', LESSON_OTHER_ANSWERS], array_column($answers, 'answer'));

        // Matching: two feedback answers, then the pairs.
        $answers = $this->answers($pages[5]->id);
        $this->assertCount(4, $answers);
        $this->assertSame(['Cat', 'Dog'], [$answers[2]->answer, $answers[3]->answer]);
        $this->assertSame(['Meow', 'Woof'], [$answers[2]->response, $answers[3]->response]);

        // Essay: one answer with the jump.
        $answers = $this->answers($pages[6]->id);
        $this->assertCount(1, $answers);
        $this->assertEquals(LESSON_EOL, $answers[0]->jumpto);

        // End of branch: back to the content page.
        $answers = $this->answers($pages[7]->id);
        $this->assertEquals($pages[0]->id, $answers[0]->jumpto);
    }

    /**
     * The wizard's answers become the handler's fields; a page can go at the start.
     */
    public function test_through_engine(): void {
        $this->resetAfterTest();
        $cm = $this->make_lesson();
        $doc = $this->doc();
        content_creator::save($doc, $cm, ['kind' => 'content', 'title' => 'First', 'contents' => '', 'button' => [1 => 'Next']]);

        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers([
            'kind' => 'multichoice',
            'title' => 'Opening question',
            'question' => ['text' => '<p>Which is a fruit?</p>', 'format' => FORMAT_HTML],
            'choice1' => 'Apple',
            'choice2' => 'Chair',
            'choice3' => '',
            'choice4' => '',
            'correct' => '1',
            'rightjump' => 'next',
            'rightfeedback' => 'Well done',
            'wrongjump' => 'this',
            'wrongfeedback' => '',
            'button1' => 'Ignored, not a content page',
            'position' => 'start',
        ]);
        $this->assertArrayNotHasKey('button1', $answers);
        $fields = $engine->fields($answers);
        $this->assertSame('multichoice', $fields['kind']);
        $this->assertSame('Apple', $fields['choice'][1]);
        $this->assertSame('start', $fields['position']);
        $this->assertSame([], $engine->check($answers));
        $this->assertSame([], content_creator::check($doc, $cm, $fields));

        $this->assertSame('Page 1: Opening question', content_creator::save($doc, $cm, $fields));
        $pages = $this->pages($cm);
        $this->assertSame(['Opening question', 'First'], array_column($pages, 'title'));
        $answers = $this->answers($pages[0]->id);
        $this->assertSame(['Apple', 'Chair'], array_column($answers, 'answer'));
        $this->assertSame('<p>Well done</p>', $answers[0]->response);
    }

    /**
     * The end of a branch is offered only after a content page.
     */
    public function test_offered(): void {
        $this->resetAfterTest();
        $cm = $this->make_lesson();
        $this->assertNotContains('endofbranch', lesson_page::offered($cm)['kind']);
        content_creator::save($this->doc(), $cm, ['kind' => 'content', 'title' => 'Menu', 'button' => [1 => 'Go']]);
        $this->assertContains('endofbranch', lesson_page::offered($cm)['kind']);
    }

    /**
     * Pages Moodle would refuse or misunderstand are refused, and nothing is saved.
     */
    public function test_refused(): void {
        global $DB;
        $this->resetAfterTest();
        $cm = $this->make_lesson(['maxanswers' => 2]);
        $doc = $this->doc();
        $q = ['text' => '<p>Q</p>', 'format' => FORMAT_HTML];

        $errors = content_creator::check($doc, $cm, ['kind' => 'multichoice', 'title' => 'Q', 'question' => $q,
            'choice' => [1 => 'Only one'], 'correct' => '2']);
        $this->assertArrayHasKey('choice', $errors);
        $this->assertArrayHasKey('correct', $errors);

        $errors = content_creator::check($doc, $cm, ['kind' => 'content', 'title' => 'Menu',
            'button' => [1 => 'A', 2 => 'B', 3 => 'C']]);
        $this->assertSame(['button' => get_string('lessonpage_error_toomany', 'tool_wizards', 2)], $errors);

        $errors = content_creator::check($doc, $cm, ['kind' => 'endofbranch']);
        $this->assertSame(['kind' => get_string('lessonpage_error_nobranch', 'tool_wizards')], $errors);

        $errors = content_creator::check($doc, $cm, ['kind' => 'matching', 'title' => 'M', 'question' => $q,
            'item' => [1 => 'Cat', 2 => 'Dog'], 'match' => [1 => 'Meow']]);
        $this->assertArrayHasKey('match', $errors);

        try {
            content_creator::save($doc, $cm, ['kind' => 'shortanswer', 'title' => '', 'question' => $q]);
            $this->fail('A page without a title and answers is refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_moduleform', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('lesson_pages', ['lessonid' => $cm->instance]));

        // Without custom scoring, a wrong answer that moves on would count as right.
        $plain = $this->make_lesson(['custom' => 0]);
        $errors = content_creator::check($doc, $plain, ['kind' => 'truefalse', 'title' => 'T', 'question' => $q,
            'truefalse' => '1', 'wrongjump' => 'next']);
        $this->assertSame(['wrongjump' => get_string('lessonpage_error_wrongforward', 'tool_wizards')], $errors);
    }
}
