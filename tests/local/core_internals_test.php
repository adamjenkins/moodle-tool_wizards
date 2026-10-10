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
 * The parts of core's forms that the wizards rely on but that are not a public API.
 *
 * The wizards submit answers through core's own forms, so they read some of those forms' internals
 * (form_submission, course_defaults_form, introspector). If a Moodle release changes one of them, this
 * test fails with its name, rather than a wizard quietly submitting the wrong data.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(form_submission::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(course_defaults_form::class)]
final class core_internals_test extends advanced_testcase {
    /**
     * The moodleform members read through reflection or a subclass.
     */
    public function test_moodleform_members(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        foreach (['_form', '_definition_finalized'] as $name) {
            $this->assertTrue(property_exists(\moodleform::class, $name), "moodleform::\$$name is gone");
        }
    }

    /**
     * The MoodleQuickForm members read directly.
     */
    public function test_quickform_members(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $mform = new \MoodleQuickForm('tool_wizards_internals', 'post', '');
        $mform->addElement('text', 'name', 'Name');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addElement('advcheckbox', 'flag', 'Flag');
        $mform->disabledIf('name', 'flag', 'checked');
        $mform->setDefault('name', 'x');

        foreach (['_elements', '_errors', '_defaultValues', '_rules'] as $name) {
            $this->assertTrue(property_exists($mform, $name), "MoodleQuickForm::\$$name is gone");
            $this->assertTrue((new \ReflectionProperty($mform, $name))->isPublic(), "MoodleQuickForm::\$$name is no longer public");
        }
        $this->assertNotEmpty($mform->_elements);
        $this->assertArrayHasKey('name', $mform->_rules);
        $this->assertSame('x', $mform->_defaultValues['name']);

        $this->assertTrue(method_exists($mform, 'getLockOptionObject'), 'MoodleQuickForm::getLockOptionObject() is gone');
        $lock = $mform->getLockOptionObject();
        $this->assertIsArray($lock);
        $this->assertCount(2, $lock);
        $this->assertArrayHasKey('flag', $lock[1], 'getLockOptionObject() no longer keys dependencies by their field');
    }
}
