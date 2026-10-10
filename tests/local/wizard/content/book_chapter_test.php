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
 * Tests for adding a chapter to a book.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(book_chapter::class)]
final class book_chapter_test extends \advanced_testcase {
    /**
     * A course, a teacher (the current user) and an empty book.
     *
     * @return \cm_info the book
     */
    protected function setup_book(): \cm_info {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $book = $generator->create_module('book', ['course' => $course->id]);
        return get_fast_modinfo($course)->get_cm($book->cmid);
    }

    /**
     * A chapter saved from fields, then a subchapter from the wizard's answers, both at the end.
     */
    public function test_save(): void {
        global $DB;
        $cm = $this->setup_book();
        $doc = defaults::shipped()['bookchapter'];
        $this->assertSame(['subchapter' => ['0']], book_chapter::offered($cm));

        $sink = $this->redirectEvents();
        $added = content_creator::save($doc, $cm, [
            'title' => 'Welcome',
            'content' => ['text' => '<p>Hello</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'subchapter' => 0,
        ]);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \mod_book\event\chapter_created);
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame(get_string('bookchapter_saved', 'tool_wizards', 'Welcome'), $added);
        $first = $DB->get_record('book_chapters', ['bookid' => $cm->instance, 'title' => 'Welcome'], '*', MUST_EXIST);
        $this->assertEquals(1, $first->pagenum);
        $this->assertEquals(0, $first->subchapter);
        $this->assertSame('<p>Hello</p>', $first->content);
        $this->assertSame(['subchapter' => ['0', '1']], book_chapter::offered($cm));

        // Through the engine: the answers become the fields.
        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers([
            'title' => 'Details',
            'level' => 1,
            'content' => ['text' => '<p>More</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
        ]);
        $this->assertSame([], $engine->check($answers));
        $fields = $engine->fields($answers);
        $this->assertSame(1, $fields['subchapter']);
        content_creator::save($doc, $cm, $fields);
        $second = $DB->get_record('book_chapters', ['bookid' => $cm->instance, 'title' => 'Details'], '*', MUST_EXIST);
        $this->assertEquals(2, $second->pagenum);
        $this->assertEquals(1, $second->subchapter);
    }

    /**
     * A chapter without a title or text is refused, and nothing is saved.
     */
    public function test_refused(): void {
        global $DB;
        $cm = $this->setup_book();
        $fields = ['title' => ' ', 'content' => ['text' => '<p></p>', 'format' => FORMAT_HTML]];
        $errors = book_chapter::check($cm, $fields);
        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('content', $errors);
        try {
            content_creator::save(defaults::shipped()['bookchapter'], $cm, $fields);
            $this->fail('Expected the chapter to be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_moduleform', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('book_chapters', ['bookid' => $cm->instance]));
    }
}
