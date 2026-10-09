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

namespace tool_wizards\local\wizard;

use advanced_testcase;

/**
 * Tests for the wizard engine: conditions, answers, field mapping, error placement, and text.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(text::class)]
final class engine_test extends advanced_testcase {
    /**
     * Answers on screens that do not apply are dropped; choices and presets map to fields.
     */
    public function test_answers_and_fields(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $engine = new engine(validator_test::minimal(), 0, $course);

        $answers = $engine->answers(['name' => ' Talk ', 'kind' => 'a', 'subs' => '2']);
        $this->assertSame(['name' => 'Talk', 'kind' => 'a', 'subs' => '2'], $answers);
        $this->assertSame(['name' => 'Talk', 'type' => 'general', 'forcesubscribe' => '2'], $engine->fields($answers));

        // The emails screen applies to kind a only: for kind b its answer is not used.
        $answers = $engine->answers(['name' => 'Talk', 'kind' => 'b', 'subs' => '2']);
        $this->assertArrayNotHasKey('subs', $answers);
        $this->assertSame(['name' => 'Talk', 'type' => 'qanda'], $engine->fields($answers));

        // A choice that does not exist is not an answer.
        $this->assertArrayNotHasKey('kind', $engine->answers(['name' => 'Talk', 'kind' => 'zzz']));

        $this->assertSame(['name' => get_string('required')], $engine->check($engine->answers(['name' => '', 'kind' => 'a'])));
        $this->assertSame(['kind' => ['a' => ['subs' => 2]]], $engine->presets());
    }

    /**
     * Course facts decide screens before the teacher sees them; answer conditions are left to the browser.
     */
    public function test_client_conditions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $engine = new engine(validator_test::minimal(), 0, $course);
        $keys = array_column($engine->screens(), 'key');
        $this->assertSame(['basics', 'emails'], $keys, 'No completion screen without completion tracking.');
        $this->assertSame(['answer' => 'kind', 'is' => 'a'], $engine->screens()[1]['cond']);
        // A fact that holds drops out; the answer condition stays for the browser.
        $this->assertSame(['answer' => 'x', 'is' => 1], $engine->client_condition(['all' => [['answer' => 'x', 'is' => 1],
            ['not' => ['course' => 'groupmodeforce']]]]));
        $this->assertFalse($engine->client_condition(['course' => 'groupmodeforce']));
    }

    /**
     * Fields are set by their form names; core's errors land on the question that sets them.
     */
    public function test_set_field_and_errors(): void {
        $this->resetAfterTest();
        $fields = [];
        engine::set_field($fields, 'grade_forum[modgrade_point]', '10');
        engine::set_field($fields, 'name', 'x');
        $this->assertSame(['grade_forum' => ['modgrade_point' => '10'], 'name' => 'x'], $fields);

        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $engine = new engine(validator_test::minimal(), 0, $course);
        $placed = $engine->place_errors(['type' => 'Bad type', 'forcesubscribe' => 'Bad', 'somethingelse' => 'Other']);
        $this->assertSame(['kindgroup' => 'Bad type', 'subs' => 'Bad', 'wizarderrors' => 'Other'], $placed);
    }

    /**
     * Text: the user's language inline, then the language pack, then the site language, then English.
     */
    public function test_text(): void {
        $this->resetAfterTest();
        $this->assertSame('Hola', text::get(['en' => 'Hello', 'es' => 'Hola'], 'es'));
        $this->assertSame('Hello', text::get(['en' => 'Hello', 'es' => 'Hola'], 'fr'));
        $this->assertSame(get_string('step_review', 'tool_wizards'), text::get(['string' => 'step_review'], 'en'));
        $this->assertSame('Bilan', text::get(['string' => 'step_review', 'fr' => 'Bilan'], 'fr'));
        $this->assertSame(['string' => 'x', 'fr' => 'Y'], text::with_language(['string' => 'x'], 'fr', 'Y'));
        $this->assertSame(['string' => 'x'], text::with_language(['string' => 'x', 'fr' => 'Y'], 'fr', ''));
    }
}
