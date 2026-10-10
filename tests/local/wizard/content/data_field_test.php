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
 * Tests for adding a field to a database activity.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(data_field::class)]
final class data_field_test extends \advanced_testcase {
    /**
     * A course, a teacher (the current user) and a database without fields.
     *
     * @return \cm_info the database
     */
    protected function setup_database(): \cm_info {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $data = $generator->create_module('data', ['course' => $course->id]);
        return get_fast_modinfo($course)->get_cm($data->cmid);
    }

    /**
     * One field of each kind the wizard offers, saved from fields; one through the engine.
     */
    public function test_save(): void {
        global $DB;
        $cm = $this->setup_database();
        $doc = defaults::shipped()['databasefield'];
        $this->assertEqualsCanonicalizing(array_keys(data_field::TYPES), data_field::offered($cm)['type']);

        foreach (array_keys(data_field::TYPES) as $type) {
            $fields = ['type' => $type, 'name' => 'Field ' . $type, 'description' => 'About ' . $type, 'required' => 1];
            if (in_array($type, data_field::WITHOPTIONS, true)) {
                $fields['options'] = "Red\n\nGreen\nBlue\n";
            }
            $added = content_creator::save($doc, $cm, $fields);
            $this->assertSame(get_string('databasefield_saved', 'tool_wizards', 'Field ' . $type), $added);
            $field = $DB->get_record('data_fields', ['dataid' => $cm->instance, 'name' => 'Field ' . $type], '*', MUST_EXIST);
            $this->assertSame($type, $field->type);
            $this->assertEquals(1, $field->required);
            if (in_array($type, data_field::WITHOPTIONS, true)) {
                $this->assertSame("Red\nGreen\nBlue", $field->param1);
            }
        }
        $this->assertEquals('1', $DB->get_field('data_fields', 'param1', ['dataid' => $cm->instance, 'type' => 'url']));

        // Through the engine.
        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers(['fieldtype' => 'menu', 'name' => 'Country', 'description' => '', 'required' => 0,
            'options' => "Japan\nFrance"]);
        $this->assertSame([], $engine->check($answers));
        $fields = $engine->fields($answers);
        $this->assertSame('menu', $fields['type']);
        content_creator::save($doc, $cm, $fields);
        $this->assertSame(
            "Japan\nFrance",
            $DB->get_field('data_fields', 'param1', ['dataid' => $cm->instance, 'name' => 'Country'])
        );
    }

    /**
     * An unknown kind, a name already used, and a menu without options are refused.
     */
    public function test_refused(): void {
        $cm = $this->setup_database();
        content_creator::save(defaults::shipped()['databasefield'], $cm, ['type' => 'text', 'name' => 'Title']);

        $this->assertArrayHasKey('type', data_field::check($cm, ['type' => 'nosuchtype', 'name' => 'X']));
        $errors = data_field::check($cm, ['type' => 'menu', 'name' => 'title', 'options' => " \n "]);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('options', $errors);
        $this->assertSame([], data_field::check($cm, ['type' => 'text', 'name' => 'Author']));
    }
}
