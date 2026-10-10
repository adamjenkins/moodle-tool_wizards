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
 * Tests for adding an entry to a glossary.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(glossary_entry::class)]
final class glossary_entry_test extends \advanced_testcase {
    /**
     * A course, a teacher (the current user) and a glossary.
     *
     * @param array $settings the glossary's settings
     * @return \cm_info the glossary
     */
    protected function setup_glossary(array $settings = []): \cm_info {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $glossary = $generator->create_module('glossary', ['course' => $course->id] + $settings);
        return get_fast_modinfo($course)->get_cm($glossary->cmid);
    }

    /**
     * An entry saved from fields, with keywords and linking; then one from the wizard's answers.
     */
    public function test_save(): void {
        global $DB;
        $cm = $this->setup_glossary(['usedynalink' => 1, 'allowduplicatedentries' => 0]);
        $doc = defaults::shipped()['glossaryentry'];
        $this->assertSame(['usedynalink' => ['0', '1']], glossary_entry::offered($cm));

        $added = content_creator::save($doc, $cm, [
            'concept' => 'Photosynthesis',
            'definition' => ['text' => '<p>How plants make food from light.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'aliases' => "photosynthesise\nphotosynthetic",
            'usedynalink' => 1,
            'casesensitive' => 0,
            'fullmatch' => 1,
        ]);
        // A teacher's entry is approved at once.
        $this->assertSame(get_string('glossaryentry_saved', 'tool_wizards', 'Photosynthesis'), $added);
        $entry = $DB->get_record(
            'glossary_entries',
            ['glossaryid' => $cm->instance, 'concept' => 'Photosynthesis'],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(1, $entry->approved);
        $this->assertEquals(1, $entry->usedynalink);
        $this->assertEquals(1, $entry->fullmatch);
        $this->assertEquals(2, $DB->count_records('glossary_alias', ['entryid' => $entry->id]));

        // Through the engine.
        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers([
            'concept' => 'Chlorophyll',
            'definition' => ['text' => '<p>The green in leaves.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'aliases' => '',
        ]);
        $this->assertSame([], $engine->check($answers));
        content_creator::save($doc, $cm, $engine->fields($answers));
        $this->assertTrue($DB->record_exists('glossary_entries', ['glossaryid' => $cm->instance, 'concept' => 'Chlorophyll']));
    }

    /**
     * A term already in a glossary without duplicates, a missing definition and a reserved
     * one-character keyword are refused.
     */
    public function test_refused(): void {
        $cm = $this->setup_glossary(['allowduplicatedentries' => 0]);
        $doc = defaults::shipped()['glossaryentry'];
        $definition = ['text' => '<p>A meaning.</p>', 'format' => FORMAT_HTML, 'itemid' => 0];
        content_creator::save($doc, $cm, ['concept' => 'Cell', 'definition' => $definition]);

        $errors = glossary_entry::check($cm, ['concept' => 'cell', 'definition' => ['text' => ''], 'aliases' => "?"]);
        $this->assertArrayHasKey('concept', $errors);
        $this->assertArrayHasKey('definition', $errors);
        $this->assertArrayHasKey('aliases', $errors);
        $this->assertSame([], glossary_entry::check($cm, ['concept' => 'Nucleus', 'definition' => $definition]));
    }
}
