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

namespace tool_wizards\local;

use advanced_testcase;

/**
 * Tests for carrying the wizard's answers over to the standard course form.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prefill::class)]
final class prefill_test extends advanced_testcase {
    /**
     * Build the standard new-course form as course/edit.php does, and return its finalised defaults.
     *
     * @param \core_course_category $category the category
     * @return array element name => default value
     */
    protected function standard_form_defaults(\core_course_category $category): array {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/course/edit_form.php');
        $PAGE->set_url(new \moodle_url('/course/edit.php'));
        $editoroptions = ['maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->maxbytes, 'trusttext' => false,
            'noclean' => true, 'context' => \context_coursecat::instance($category->id), 'subdirs' => 0];
        $course = file_prepare_standard_editor(null, 'summary', $editoroptions, null, 'course', 'summary', null);
        $form = new \course_edit_form(null, ['course' => $course, 'category' => $category,
            'editoroptions' => $editoroptions, 'returnto' => 0, 'returnurl' => new \moodle_url('/course/')]);
        $form->render();
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        return $mform->_defaultValues;
    }

    /**
     * The standard form opens with the wizard's answers, once.
     */
    public function test_answers_reach_standard_form_once(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();

        $answers = course_creator::normalise_answers(['fullname' => 'Carried over', 'shortname' => 'CARRY',
            'category' => $category->id, 'format' => 'weeks', 'numsections' => 9,
            'startdate' => make_timestamp(2027, 1, 11), 'visible' => '0']);
        prefill::stash($answers, $category->id);

        $defaults = $this->standard_form_defaults($category);
        $this->assertSame('Carried over', $defaults['fullname']);
        $this->assertSame('CARRY', $defaults['shortname']);
        $this->assertSame('weeks', $defaults['format']);
        $this->assertEquals(9, $defaults['numsections']);
        $this->assertEquals(make_timestamp(2027, 1, 11), $defaults['startdate']);
        $this->assertEquals(0, $defaults['visible']);
        $this->assertObjectNotHasProperty('tool_wizards_prefill', $SESSION, 'The answers are used once.');

        $again = $this->standard_form_defaults($category);
        $this->assertNotSame('Carried over', $again['fullname'] ?? '');
    }

    /**
     * Answers for one category are not applied to another, nor after they have gone stale.
     */
    public function test_answers_only_for_their_category_and_while_fresh(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_category();
        $answers = course_creator::normalise_answers(['fullname' => 'Elsewhere', 'shortname' => 'ELSE',
            'category' => $category->id, 'format' => 'topics']);

        prefill::stash($answers, $category->id);
        $this->assertNotSame('Elsewhere', $this->standard_form_defaults($other)['fullname'] ?? '');

        prefill::stash($answers, $category->id);
        $SESSION->tool_wizards_prefill->time -= prefill::LIFETIME + 1;
        $this->assertNotSame('Elsewhere', $this->standard_form_defaults($category)['fullname'] ?? '');
    }
}
