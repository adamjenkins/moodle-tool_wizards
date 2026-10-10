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
 * Tests for adding an option to a choice.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(choice_option::class)]
final class choice_option_test extends \advanced_testcase {
    /**
     * A course, a teacher (the current user) and a choice with options A and B.
     *
     * @param array $settings the choice's settings
     * @return \cm_info the choice
     */
    protected function setup_choice(array $settings = []): \cm_info {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $choice = $generator->create_module('choice', ['course' => $course->id, 'option' => ['A', 'B']] + $settings);
        return get_fast_modinfo($course)->get_cm($choice->cmid);
    }

    /**
     * An option is added beside the existing ones, keeping a student's answer.
     */
    public function test_save(): void {
        global $DB;
        $cm = $this->setup_choice();
        $doc = defaults::shipped()['choiceoption'];
        $this->assertSame(['limitanswers' => ['0']], choice_option::offered($cm));
        $optiona = $this->option($cm, 'A');
        $student = $this->getDataGenerator()->create_and_enrol($cm->get_course(), 'student');
        $DB->insert_record('choice_answers', (object) ['choiceid' => $cm->instance, 'userid' => $student->id,
            'optionid' => $optiona->id, 'timemodified' => time()]);

        $sink = $this->redirectEvents();
        $added = content_creator::save($doc, $cm, ['text' => 'C']);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\course_module_updated);
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame(get_string('choiceoption_saved', 'tool_wizards', 'C'), $added);
        $this->assertEquals(3, $DB->count_records('choice_options', ['choiceid' => $cm->instance]));
        $this->assertTrue($DB->record_exists('choice_answers', ['optionid' => $optiona->id, 'userid' => $student->id]));

        // Through the engine: the limit is not asked when the choice does not limit answers.
        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers(['text' => 'D', 'limit' => 5]);
        $this->assertArrayNotHasKey('limit', $answers);
        content_creator::save($doc, $cm, $engine->fields($answers));
        $this->assertEquals(0, $this->option($cm, 'D')->maxanswers);
    }

    /**
     * When the choice limits answers, the wizard sets the new option's limit.
     */
    public function test_limit(): void {
        global $DB;
        $cm = $this->setup_choice(['limitanswers' => 1, 'limit' => [10, 10]]);
        $doc = defaults::shipped()['choiceoption'];
        $this->assertSame(['limitanswers' => ['1']], choice_option::offered($cm));
        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers(['text' => 'C', 'limit' => '7']);
        $this->assertSame([], $engine->check($answers));
        content_creator::save($doc, $cm, $engine->fields($answers));
        $this->assertEquals(7, $this->option($cm, 'C')->maxanswers);
    }

    /**
     * An empty option, one the choice already has, and a limit that is not a whole number are refused.
     */
    public function test_refused(): void {
        $cm = $this->setup_choice();
        $this->assertArrayHasKey('text', choice_option::check($cm, ['text' => '  ']));
        $this->assertArrayHasKey('text', choice_option::check($cm, ['text' => 'a']));
        $this->assertArrayHasKey('limit', choice_option::check($cm, ['text' => 'C', 'limit' => '-1']));
        $this->assertSame([], choice_option::check($cm, ['text' => 'C', 'limit' => '3']));
    }

    /**
     * One of the choice's options, by its text (a text column cannot be compared in SQL on every database).
     *
     * @param \cm_info $cm the choice
     * @param string $text the option's text
     * @return \stdClass
     */
    protected function option(\cm_info $cm, string $text): \stdClass {
        global $DB;
        $matches = array_filter(
            $DB->get_records('choice_options', ['choiceid' => $cm->instance]),
            fn($option) => $option->text === $text
        );
        $this->assertCount(1, $matches, $text);
        return reset($matches);
    }
}
