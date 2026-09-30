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
 * Tests for when the first-content suggestions appear.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prompt::class)]
final class prompt_test extends advanced_testcase {
    /** @var \stdClass the course */
    protected \stdClass $course;

    /** @var \stdClass an editing teacher */
    protected \stdClass $teacher;

    /**
     * An empty course (apart from its Announcements forum) and its teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2, 'newsitems' => 5]);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        forum_get_course_forum($this->course->id, 'news');
    }

    /**
     * Render the course page's addition the way the hook does.
     *
     * @return string
     */
    protected function render_prompt(): string {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $this->course->id]));
        $PAGE->set_course($this->course);
        $PAGE->set_context(\context_course::instance($this->course->id));
        return prompt::render_for_page($PAGE, $PAGE->get_renderer('core'));
    }

    /**
     * The Announcements forum does not count as content.
     */
    public function test_count_content_ignores_announcements(): void {
        $this->assertSame(1, count(get_fast_modinfo($this->course)->get_cms()), 'The course has its Announcements forum.');
        $this->assertSame(0, prompt::count_content($this->course));
        $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->assertSame(1, prompt::count_content($this->course));
    }

    /**
     * Shown on a nearly empty course, not beyond the threshold.
     */
    public function test_threshold(): void {
        set_config('emptythreshold', 1, 'tool_wizards');
        $this->assertStringContainsString('tool_wizards-firstcontent', $this->render_prompt());

        $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->assertStringContainsString('tool_wizards-firstcontent', $this->render_prompt(), 'One activity is "little".');

        $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->assertSame('', $this->render_prompt());
    }

    /**
     * Straight after the wizard created the course, or a mini-wizard added something, it is shown
     * whatever the threshold, with the matching message; and only once.
     */
    public function test_just_created_and_just_added(): void {
        set_config('emptythreshold', 0, 'tool_wizards');
        $cm = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'name' => 'Reading list']);
        $this->assertSame('', $this->render_prompt());

        prompt::mark_just_created($this->course->id);
        $this->assertStringContainsString(get_string('prompt_ready', 'tool_wizards'), $this->render_prompt());
        $this->assertSame('', $this->render_prompt(), 'Only once.');

        prompt::mark_just_added($this->course->id, $cm->cmid);
        $html = $this->render_prompt();
        $this->assertStringContainsString(get_string('prompt_added', 'tool_wizards', 'Reading list'), $html);
        $this->assertStringNotContainsString('data-type="page"', $html, 'Suggests something different next.');
    }

    /**
     * Hidden for a course, or for good, or for people who cannot add content.
     */
    public function test_dismissal_and_access(): void {
        $this->assertNotSame('', $this->render_prompt());

        prompt::dismiss_course($this->course->id);
        $this->assertSame('', $this->render_prompt());
        $other = $this->getDataGenerator()->create_course();
        $this->assertFalse(prompt::is_dismissed($other->id, $this->teacher->id), 'Only that course.');

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertNotSame('', $this->render_prompt(), 'Another teacher still sees it.');
        set_user_preference(prompt::PREF_HIDE, 1);
        $this->assertSame('', $this->render_prompt());
        set_user_preference(prompt::PREF_HIDE, 0);
        $this->assertNotSame('', $this->render_prompt(), 'Switching the preference back brings it back.');

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $this->assertSame('', $this->render_prompt());
    }

    /**
     * The footer's help popover offers "Bring the wizards back" on a course page only where the
     * user hid the suggestions and they would show again.
     */
    public function test_bring_back_link(): void {
        $label = get_string('bringback', 'tool_wizards');
        $this->assertStringNotContainsString($label, $this->footer_html(), 'Nothing to bring back yet.');

        prompt::dismiss_course($this->course->id);
        $html = $this->footer_html();
        $this->assertStringContainsString($label, $html);
        $this->assertStringContainsString('/admin/tool/wizards/showagain.php?courseid=' . $this->course->id, $html);
        $this->assertStringContainsString('sesskey=' . sesskey(), $html);

        $other = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $other->id, 'editingteacher');
        $this->assertStringNotContainsString($label, $this->footer_html($other), 'Only on the course where it was hidden.');

        $this->assertStringNotContainsString($label, $this->footer_html(null, '/course/edit.php'), 'Only on the course page.');

        set_user_preference(prompt::PREF_HIDE, 1);
        $this->assertStringNotContainsString($label, $this->footer_html(), 'Not while suggestions are off everywhere.');
        set_user_preference(prompt::PREF_HIDE, 0);

        set_config('emptythreshold', 0, 'tool_wizards');
        $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->assertStringNotContainsString($label, $this->footer_html(), 'Not when the card would not show anyway.');
        set_config('emptythreshold', 1, 'tool_wizards');
        $this->assertStringContainsString($label, $this->footer_html());

        set_config('enabled', 0, 'tool_wizards');
        $this->assertStringNotContainsString($label, $this->footer_html(), 'Not when the site switch is off.');
        set_config('enabled', 1, 'tool_wizards');

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        prompt::dismiss_course($this->course->id);
        $this->assertStringNotContainsString($label, $this->footer_html(), 'Not for someone who cannot add content.');
    }

    /**
     * Bringing the suggestions back undoes one course's dismissal for the current user only.
     */
    public function test_undismiss_course(): void {
        $other = $this->getDataGenerator()->create_course();
        prompt::dismiss_course($this->course->id);
        prompt::dismiss_course($other->id);
        $colleague = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($colleague);
        prompt::dismiss_course($this->course->id);
        $this->setUser($this->teacher);

        prompt::undismiss_course($this->course->id);
        $this->assertFalse(prompt::is_dismissed($this->course->id, $this->teacher->id));
        $this->assertNotSame('', $this->render_prompt(), 'The card is back.');
        $this->assertTrue(prompt::is_dismissed($other->id, $this->teacher->id), 'Other courses stay hidden.');
        $this->assertTrue(prompt::is_dismissed($this->course->id, $colleague->id), 'Other users keep theirs.');
    }

    /**
     * The footer HTML the standard footer hook collects on a page of a course.
     *
     * @param \stdClass|null $course the course, or null for the test course
     * @param string $path the page's script
     * @return string
     */
    protected function footer_html(?\stdClass $course = null, string $path = '/course/view.php'): string {
        global $PAGE;
        $course = $course ?? $this->course;
        $PAGE = new \moodle_page();
        $PAGE->set_url(new \moodle_url($path, ['id' => $course->id]));
        $PAGE->set_course($course);
        $PAGE->set_context(\context_course::instance($course->id));
        $hook = new \core\hook\output\before_standard_footer_html_generation($PAGE->get_renderer('core'));
        \tool_wizards\hook_callbacks::before_standard_footer($hook);
        return $hook->get_output();
    }

    /**
     * Only the kinds of content the teacher may add are offered.
     */
    public function test_only_allowed_types_offered(): void {
        $roleid = create_role('No quizzes or glossaries', 'noquiz', '');
        foreach (['mod/forum:addinstance', 'mod/page:addinstance'] as $cap) {
            assign_capability($cap, CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
        }
        role_assign($roleid, $this->teacher->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $html = $this->render_prompt();
        $this->assertStringContainsString('data-type="file"', $html);
        $this->assertStringNotContainsString('data-type="forum"', $html);
        $this->assertStringNotContainsString('data-type="page"', $html);
    }

    /**
     * Nothing at all when the site switch is off.
     */
    public function test_site_switch(): void {
        set_config('enabled', 0, 'tool_wizards');
        $hook = new \core\hook\output\after_http_headers($this->page_renderer());
        \tool_wizards\hook_callbacks::after_http_headers($hook);
        $this->assertSame('', $hook->get_output());

        set_config('enabled', 1, 'tool_wizards');
        $hook = new \core\hook\output\after_http_headers($this->page_renderer());
        \tool_wizards\hook_callbacks::after_http_headers($hook);
        $this->assertStringContainsString('tool_wizards-firstcontent', $hook->get_output());
    }

    /**
     * A renderer for a fresh course page.
     *
     * @return \renderer_base
     */
    protected function page_renderer(): \renderer_base {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $this->course->id]));
        $PAGE->set_course($this->course);
        $PAGE->set_context(\context_course::instance($this->course->id));
        return $PAGE->get_renderer('core');
    }
}
