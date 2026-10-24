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

namespace tool_wizards;

use advanced_testcase;
use core_courseformat\output\local\content\activitychooserbutton;

/**
 * Tests for "Add with a wizard" in the course page's add menus.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
final class hook_callbacks_test extends advanced_testcase {
    /**
     * Our entry in an add button's menu, as core exports the button (which dispatches the hook).
     *
     * @param \stdClass $course the course
     * @param \cm_info|null $cm the activity the button is before, or null for the section's own button
     * @return array|null attribute name => value, or null when there is no entry
     */
    protected function entry(\stdClass $course, ?\cm_info $cm = null): ?array {
        global $PAGE;
        $section = get_fast_modinfo($course)->get_section_info(1);
        $data = (new activitychooserbutton($section, $cm))->export_for_template($PAGE->get_renderer('core'));
        foreach ($data->actionlinks as $link) {
            $attributes = array_column($link->attributes, 'value', 'name');
            if (($attributes['data-action'] ?? '') === 'tool_wizards-addwithwizard') {
                return $attributes;
            }
        }
        return null;
    }

    /**
     * Offered on the section's own button and on the ones between activities, for a teacher only,
     * and not with the wizards switched off.
     */
    public function test_add_menu_entry(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);
        $page = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->setUser($generator->create_and_enrol($course, 'editingteacher'));
        $entry = $this->entry($course);
        $this->assertNotNull($entry);
        $this->assertEquals(1, $entry['data-sectionnum']);
        $this->assertArrayNotHasKey('data-beforemod', $entry);
        $this->assertEquals($cm->id, $this->entry($course, $cm)['data-beforemod'], 'Between activities: before this one.');

        $this->setUser($generator->create_and_enrol($course, 'student'));
        $this->assertNull($this->entry($course), 'Nothing to offer someone who cannot add content.');

        $this->setUser($generator->create_and_enrol($course, 'editingteacher'));
        set_config('enabled', 0, 'tool_wizards');
        $this->assertNull($this->entry($course));
    }
}
