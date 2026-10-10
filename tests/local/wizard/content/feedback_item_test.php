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
 * Tests for adding a question to a feedback survey.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(feedback_item::class)]
final class feedback_item_test extends \advanced_testcase {
    /**
     * A course, a teacher (the current user) and a feedback without questions.
     *
     * @return \cm_info the feedback
     */
    protected function setup_feedback(): \cm_info {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $feedback = $generator->create_module('feedback', ['course' => $course->id]);
        return get_fast_modinfo($course)->get_cm($feedback->cmid);
    }

    /**
     * One item of each kind, saved from fields, each at the end; then one through the engine.
     */
    public function test_save(): void {
        global $DB;
        $cm = $this->setup_feedback();
        $doc = defaults::shipped()['feedbackquestion'];
        $kinds = [
            'multichoice' => ['name' => 'How was it?', 'required' => 1, 'values' => "Good\nFine\n\nBad", 'subtype' => 'c'],
            'textfield' => ['name' => 'Your town'],
            'textarea' => ['name' => 'What would you change?'],
            'numeric' => ['name' => 'Hours of study', 'rangefrom' => '40', 'rangeto' => '0'],
            'label' => ['text' => ['text' => '<p>Thank you.</p>', 'format' => FORMAT_HTML, 'itemid' => 0]],
        ];
        $position = 0;
        foreach ($kinds as $typ => $fields) {
            content_creator::save($doc, $cm, ['typ' => $typ] + $fields);
            $position++;
            $item = $DB->get_record('feedback_item', ['feedback' => $cm->instance, 'position' => $position], '*', MUST_EXIST);
            $this->assertSame($typ, $item->typ);
        }
        $items = $DB->get_records('feedback_item', ['feedback' => $cm->instance], 'position');
        $this->assertCount(5, $items);
        [$multichoice, $textfield, $textarea, $numeric, $label] = array_values($items);
        $this->assertSame('c>>>>>Good|Fine|Bad', $multichoice->presentation);
        $this->assertEquals(1, $multichoice->required);
        $this->assertEquals(1, $multichoice->hasvalue);
        $this->assertSame('30|255', $textfield->presentation);
        $this->assertSame('30|5', $textarea->presentation);
        $this->assertSame('0|40', $numeric->presentation);
        $this->assertSame('<p>Thank you.</p>', $label->presentation);
        $this->assertEquals(0, $label->hasvalue);

        // Through the engine.
        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers(['itemtype' => 'multichoice', 'name' => 'Pick one', 'required' => 0,
            'values' => "Yes\nNo", 'subtype' => 'r']);
        $this->assertSame([], $engine->check($answers));
        $added = content_creator::save($doc, $cm, $engine->fields($answers));
        $this->assertSame(get_string('feedbackquestion_saved', 'tool_wizards', 'Pick one'), $added);
        $item = $DB->get_record('feedback_item', ['feedback' => $cm->instance, 'name' => 'Pick one'], '*', MUST_EXIST);
        $this->assertEquals(6, $item->position);
        $this->assertSame('r>>>>>Yes|No', $item->presentation);
    }

    /**
     * An unknown kind, a missing question or answers, a range that is not a number and an empty
     * piece of information are refused.
     */
    public function test_refused(): void {
        $cm = $this->setup_feedback();
        $this->assertArrayHasKey('typ', feedback_item::check($cm, ['typ' => 'captcha', 'name' => 'X']));
        $errors = feedback_item::check($cm, ['typ' => 'multichoice', 'name' => '', 'values' => '']);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('values', $errors);
        $this->assertArrayHasKey('rangeto', feedback_item::check($cm, ['typ' => 'numeric', 'name' => 'N', 'rangeto' => 'many']));
        $this->assertArrayHasKey('text', feedback_item::check($cm, ['typ' => 'label', 'text' => ['text' => '']]));
        $this->assertSame([], feedback_item::check($cm, ['typ' => 'textfield', 'name' => 'Your name']));
    }
}
