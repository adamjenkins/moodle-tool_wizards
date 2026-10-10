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
 * Tests for the workshop content handlers: assessment form, allocation, examples and phases.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(workshop_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(workshop_allocation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(workshop_example::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(workshop_phase::class)]
final class workshop_test extends \advanced_testcase {
    /**
     * A course with a teacher (logged in) and a workshop.
     *
     * @param array $settings workshop settings
     * @return array [course, cm]
     */
    protected function workshop(array $settings = []): array {
        global $PAGE;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->setUser($generator->create_and_enrol($course, 'editingteacher'));
        $workshop = $generator->create_module('workshop', ['course' => $course->id] + $settings);
        $cm = get_fast_modinfo($course)->get_cm($workshop->cmid);
        // As in the wizard's modal: the dynamic form validates the activity's context, which sets the page's.
        // A fresh page each time, so a test may use several workshops.
        $PAGE = new \moodle_page();
        $PAGE->set_context($cm->context);
        return [$course, $cm];
    }

    /**
     * A shipped definition.
     *
     * @param string $key the wizard key
     * @return array
     */
    protected static function doc(string $key): array {
        return defaults::shipped()[$key];
    }

    /**
     * Each grading strategy gets one point per run, appended after the existing ones.
     */
    public function test_form_strategies(): void {
        global $DB;
        $this->resetAfterTest();
        $doc = self::doc('workshopform');
        $editor = fn($text) => ['text' => $text, 'format' => FORMAT_HTML];

        // Comments.
        [, $cm] = $this->workshop(['strategy' => 'comments']);
        $this->assertSame(['strategy' => ['comments']], workshop_form::offered($cm));
        $this->assertTrue(workshop_form::is_available($cm));
        content_creator::save($doc, $cm, ['description' => $editor('Is the argument clear?')]);
        $added = content_creator::save($doc, $cm, ['description' => 'Are the sources named?']);
        $this->assertStringContainsString('2', $added);
        $rows = array_values($DB->get_records('workshopform_comments', ['workshopid' => $cm->instance], 'sort'));
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Is the argument clear?', $rows[0]->description);
        $this->assertStringContainsString('Are the sources named?', $rows[1]->description);
        $this->assertEquals(2, $rows[1]->sort);

        // Accumulative, through the engine.
        [, $cm] = $this->workshop(['strategy' => 'accumulative']);
        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers(['criterion' => $editor('Use of sources'), 'maxpoints' => '20', 'weight' => '2',
            'aspect' => $editor('Not this one')]);
        $this->assertArrayNotHasKey('aspect', $answers, 'Only the workshop\'s own strategy is asked.');
        $this->assertSame([], $engine->check($answers));
        $fields = $engine->fields($answers);
        $this->assertSame('20', $fields['points']);
        $this->assertSame('2', $fields['weight']);
        content_creator::save($doc, $cm, $fields);
        $row = $DB->get_record('workshopform_accumulative', ['workshopid' => $cm->instance], '*', MUST_EXIST);
        $this->assertEquals(20, $row->grade);
        $this->assertEquals(2, $row->weight);
        $this->assertStringContainsString('Use of sources', $row->description);

        // Number of errors.
        [, $cm] = $this->workshop(['strategy' => 'numerrors']);
        content_creator::save($doc, $cm, ['description' => 'Has a title', 'wordyes' => 'Present', 'wordno' => 'Missing',
            'weight' => '1']);
        $row = $DB->get_record('workshopform_numerrors', ['workshopid' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Present', $row->grade1);
        $this->assertSame('Missing', $row->grade0);

        // Rubric, through the engine, twice: the first row and its levels are kept.
        [, $cm] = $this->workshop(['strategy' => 'rubric']);
        $engine = (new engine($doc))->set_cm($cm);
        foreach (['Sources', 'Structure'] as $name) {
            $answers = $engine->answers(['rubriccriterion' => $editor($name), 'level1' => 'None', 'level1points' => '0',
                'level2' => 'Some', 'level2points' => '5', 'level3' => 'Many', 'level3points' => '10', 'level4' => '',
                'level4points' => '15']);
            content_creator::save($doc, $cm, $engine->fields($answers));
        }
        $rows = array_values($DB->get_records('workshopform_rubric', ['workshopid' => $cm->instance], 'sort'));
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Sources', $rows[0]->description);
        foreach ($rows as $row) {
            $this->assertSame(3, $DB->count_records('workshopform_rubric_levels', ['dimensionid' => $row->id]));
        }
        $this->assertEquals('list', $DB->get_field('workshopform_rubric_config', 'layout', ['workshopid' => $cm->instance]));
    }

    /**
     * The form refuses an empty point and rubric levels with the same points.
     */
    public function test_form_refused(): void {
        $this->resetAfterTest();
        $doc = self::doc('workshopform');
        [, $cm] = $this->workshop(['strategy' => 'rubric']);
        $errors = workshop_form::check($cm, ['description' => '']);
        $this->assertArrayHasKey('description', $errors);
        $this->assertArrayHasKey('leveldefinition', $errors);
        $errors = workshop_form::check($cm, ['description' => 'Sources',
            'leveldefinition' => [1 => 'None', 2 => 'Some'], 'levelpoints' => [1 => '3', 2 => '3']]);
        $this->assertSame(['levelpoints' => get_string('mustbeunique', 'workshopform_rubric')], $errors);
        $this->expectException(\moodle_exception::class);
        content_creator::save($doc, $cm, ['description' => '']);
    }

    /**
     * Reviews are given out at random; without any work handed in there is nothing to give out.
     */
    public function test_allocation(): void {
        global $DB;
        $this->resetAfterTest();
        $doc = self::doc('workshopallocation');
        [$course, $cm] = $this->workshop();
        $this->assertTrue(workshop_allocation::is_available($cm));
        $this->assertSame(['selfassessment' => ['0'], 'visiblegroups' => ['0']], workshop_allocation::offered($cm));

        $errors = content_creator::check($doc, $cm, ['numofreviews' => '2', 'numper' => '1']);
        $this->assertSame(['numofreviews' => get_string('workshopallocation_error_nowork', 'tool_wizards')], $errors);

        $generator = $this->getDataGenerator();
        $workshopgenerator = $generator->get_plugin_generator('mod_workshop');
        $submissions = [];
        for ($i = 0; $i < 3; $i++) {
            $student = $generator->create_and_enrol($course, 'student');
            $submissions[] = $workshopgenerator->create_submission($cm->instance, $student->id);
        }
        $DB->set_field('workshop', 'phase', 20, ['id' => $cm->instance]);

        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers(['numofreviews' => '2', 'numper' => '1', 'assesswosubmission' => '0',
            'removecurrent' => '0']);
        $fields = $engine->fields($answers);
        $this->assertSame([], content_creator::check($doc, $cm, $fields));
        content_creator::save($doc, $cm, $fields);
        foreach ($submissions as $submissionid) {
            $this->assertGreaterThan(0, $DB->count_records('workshop_assessments', ['submissionid' => $submissionid]));
        }

        $this->assertArrayHasKey('numofreviews', workshop_allocation::check($cm, ['numofreviews' => '0']));
        $this->assertFalse(workshop_allocation::repeatable());
    }

    /**
     * An example is saved as an example submission; it needs a workshop that uses examples.
     */
    public function test_example(): void {
        global $DB;
        $this->resetAfterTest();
        $doc = self::doc('workshopexample');
        [, $cm] = $this->workshop();
        $this->assertFalse(workshop_example::is_available($cm));

        [, $cm] = $this->workshop(['useexamples' => 1]);
        $this->assertTrue(workshop_example::is_available($cm));
        $this->assertSame(['text' => ['1'], 'file' => ['1']], workshop_example::offered($cm));

        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers(['title' => 'A good essay',
            'content' => ['text' => '<p>Once upon a time.</p>', 'format' => FORMAT_HTML]]);
        $added = content_creator::save($doc, $cm, $engine->fields($answers));
        $this->assertStringContainsString('A good essay', $added);
        $example = $DB->get_record('workshop_submissions', ['workshopid' => $cm->instance, 'example' => 1], '*', MUST_EXIST);
        $this->assertSame('A good essay', $example->title);
        $this->assertStringContainsString('Once upon a time.', $example->content);

        // Neither text nor a file: refused, as on the workshop's own example page.
        $errors = workshop_example::check($cm, ['title' => 'Empty']);
        $this->assertArrayHasKey('content', $errors);
        $this->assertArrayHasKey('title', workshop_example::check($cm, ['title' => '', 'content' => 'Text']));
    }

    /**
     * The phase wizard shows the current stage and switches to the chosen one.
     */
    public function test_phase(): void {
        global $DB;
        $this->resetAfterTest();
        $doc = self::doc('workshopphase');
        [, $cm] = $this->workshop();
        $this->assertSame(['phase' => ['10']], workshop_phase::offered($cm));

        $engine = (new engine($doc))->set_cm($cm);
        $keys = array_keys($engine->questions());
        $this->assertSame(['move10'], $keys, 'Only the setup stage\'s choice is asked.');
        $fields = $engine->fields($engine->answers(['move10' => '20']));
        $this->assertSame(['phase' => '20'], $fields);

        $this->assertSame(
            ['phase' => get_string('workshopphase_error_same', 'tool_wizards')],
            workshop_phase::check($cm, ['phase' => '10'])
        );
        content_creator::save($doc, $cm, $fields);
        $this->assertEquals(20, $DB->get_field('workshop', 'phase', ['id' => $cm->instance]));
        $this->assertFalse(workshop_phase::repeatable());
    }
}
