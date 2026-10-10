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

use advanced_testcase;
use tool_wizards\local\content_creator;
use tool_wizards\local\question_categories;
use tool_wizards\local\wizard\defaults;
use tool_wizards\local\wizard\engine;

/**
 * Tests for setting up a question bank's categories, and for choosing a category for a new question.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(qbank_category::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(question_categories::class)]
final class qbank_category_test extends advanced_testcase {
    /** @var \cm_info the question bank */
    protected \cm_info $cm;

    /**
     * A course with a question bank, and its teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $this->cm = get_fast_modinfo($course)->get_cm($qbank->cmid);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
    }

    /**
     * Chapters 7 to 12, each with Vocabulary and Grammar, through the wizard's own answers.
     */
    public function test_chapters_with_subcategories(): void {
        $doc = defaults::shipped()['qbankcategory'];
        $engine = (new engine($doc))->set_cm($this->cm);
        $answers = $engine->answers(['scheme' => 'chapter', 'from' => 7, 'to' => 12, 'common_vocabulary' => 1,
            'common_grammar' => 1, 'common_reading' => 0, 'others' => '']);
        $this->assertSame([], $engine->check($answers));
        $fields = $engine->fields($answers);
        $this->assertSame([], content_creator::check($doc, $this->cm, $fields));
        content_creator::save($doc, $this->cm, $fields);

        $names = array_column(question_categories::tree($this->cm->context), 'name');
        foreach (range(7, 12) as $n) {
            $this->assertContains("Chapter $n", $names);
            $this->assertContains("Chapter $n › Vocabulary", $names);
            $this->assertContains("Chapter $n › Grammar", $names);
        }
        $this->assertNotContains('Chapter 6', $names);
        $this->assertNotContains('Chapter 7 › Reading', $names);

        // Run again with one more subcategory: nothing is added twice.
        $fields['others'] = "Listening practice";
        $added = content_creator::save($doc, $this->cm, $fields);
        $this->assertStringContainsString('6', $added);
        $after = array_column(question_categories::tree($this->cm->context), 'name');
        $this->assertCount(count($names) + 6, $after);
        $this->assertContains('Chapter 9 › Listening practice', $after);

        // The bank question wizard now offers these categories, and puts a question in the chosen one.
        $qdoc = defaults::shipped()['qbankquestion'];
        $qengine = (new engine($qdoc))->set_cm($this->cm);
        $choices = array_column($qengine->questions()['category']['choices'], 'title', 'value');
        $target = array_search('Chapter 9 › Grammar', $choices);
        $this->assertNotFalse($target);
        $qanswers = $qengine->answers(['kind' => 'truefalse', 'questiontext' => ['text' => 'Sky is blue.', 'format' => 1],
            'correctanswer' => 1, 'category' => $target]);
        content_creator::save($qdoc, $this->cm, $qengine->fields($qanswers));
        global $DB;
        $this->assertEquals(1, $DB->count_records('question_bank_entries', ['questioncategoryid' => $target]));
    }

    /**
     * Topics, a word of one's own, and the refusals.
     */
    public function test_topics_custom_and_refusals(): void {
        $doc = defaults::shipped()['qbankcategory'];
        content_creator::save($doc, $this->cm, ['scheme' => 'topics', 'topics' => "Food\n\nTravel\nFood"]);
        content_creator::save($doc, $this->cm, ['scheme' => 'custom', 'label' => 'Part', 'from' => 1, 'to' => 2]);
        $names = array_column(question_categories::tree($this->cm->context), 'name');
        $this->assertContains('Food', $names);
        $this->assertContains('Travel', $names);
        $this->assertContains('Part 2', $names);

        $this->assertArrayHasKey('to', qbank_category::check($this->cm, ['scheme' => 'chapter', 'from' => 9, 'to' => 3]));
        $this->assertArrayHasKey('topics', qbank_category::check($this->cm, ['scheme' => 'topics', 'topics' => " \n "]));
        $this->assertArrayHasKey('label', qbank_category::check($this->cm, ['scheme' => 'custom', 'from' => 1, 'to' => 2]));
        $this->assertArrayHasKey('to', qbank_category::check($this->cm, ['scheme' => 'week', 'from' => 1, 'to' => 150,
            'common' => ['vocabulary' => 1]]));
        $other = $this->getDataGenerator()->create_module('qbank', ['course' => $this->cm->course]);
        $foreign = question_categories::tree(\core\context\module::instance($other->cmid))[0]['id'];
        $this->assertArrayHasKey('parent', qbank_category::check($this->cm, ['scheme' => 'unit', 'from' => 1, 'to' => 2,
            'parent' => $foreign]));
    }
}
