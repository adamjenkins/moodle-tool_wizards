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
use tool_wizards\local\messages;

/**
 * Tests for the Teacher scaffold integration and the clean-up observers.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(messages::class)]
final class observer_test extends advanced_testcase {
    /**
     * Load the stand-in event.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/fixtures/fake_tier_unlocked.php');
    }

    /**
     * An event with the contract's shape.
     *
     * @param int $userid the teacher
     * @param mixed $other the event's other data
     * @return \core\event\base
     */
    protected function unlock_event(int $userid, $other): \core\event\base {
        return \tool_wizards\event\fake_tier_unlocked::create([
            'context' => \context_system::instance(),
            'relateduserid' => $userid,
            'other' => $other,
        ]);
    }

    /**
     * The observer is registered under the exact contract name. Nothing else would notice a typo.
     */
    public function test_observer_registered(): void {
        $observers = \core\event\manager::get_all_observers();
        $this->assertArrayHasKey('\\tool_teacherscaffold\\event\\tier_unlocked', $observers);
        $callbacks = array_map(fn($o) => $o->callable, $observers['\\tool_teacherscaffold\\event\\tier_unlocked']);
        $this->assertContains('\\tool_wizards\\observer::tier_unlocked', $callbacks);
        $ours = array_values(array_filter(
            $observers['\\tool_teacherscaffold\\event\\tier_unlocked'],
            fn($o) => $o->callable === '\\tool_wizards\\observer::tier_unlocked'
        ));
        $this->assertFalse((bool) $ours[0]->internal, 'Runs after the unlocking transaction commits.');
    }

    /**
     * An unlock queues one message with the real modules, in order.
     */
    public function test_unlock_queues_message(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        observer::tier_unlocked($this->unlock_event(
            $teacher->id,
            ['tier' => 3, 'unlockedmodules' => ['quiz', 'choice', 'nosuchmod', 'quiz', 7, 'feedback']]
        ));

        $rows = $DB->get_records('tool_wizards_message', ['userid' => $teacher->id]);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame(messages::TYPE_UNLOCK, $row->type);
        $this->assertSame(['tier' => 3, 'modules' => ['quiz', 'choice', 'feedback']], json_decode($row->payload, true));
        $this->assertNull($row->timeshown);
    }

    /**
     * Malformed or empty events queue nothing and do not break the request.
     */
    public function test_malformed_events_ignored(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        observer::tier_unlocked($this->unlock_event($teacher->id, ['tier' => 2]));
        observer::tier_unlocked($this->unlock_event($teacher->id, ['tier' => 2, 'unlockedmodules' => 'quiz']));
        observer::tier_unlocked($this->unlock_event($teacher->id, ['tier' => 2, 'unlockedmodules' => ['nosuchmod']]));
        observer::tier_unlocked($this->unlock_event($teacher->id, null));
        $this->assertSame(0, $DB->count_records('tool_wizards_message'));
        $this->assertDebuggingNotCalled();
    }

    /**
     * Nothing is queued while the wizards are switched off.
     */
    public function test_nothing_queued_when_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 0, 'tool_wizards');
        $teacher = $this->getDataGenerator()->create_user();
        observer::tier_unlocked($this->unlock_event($teacher->id, ['tier' => 2, 'unlockedmodules' => ['quiz']]));
        $this->assertSame(0, $DB->count_records('tool_wizards_message'));
    }

    /**
     * The message appears once, on the next course page view, with a "try" button for a module the
     * teacher may add there, but without one when the teacher turned suggestions off.
     */
    public function test_message_shown_once(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        observer::tier_unlocked($this->unlock_event($teacher->id, ['tier' => 3, 'unlockedmodules' => ['quiz', 'choice']]));
        observer::tier_unlocked($this->unlock_event($teacher->id, ['tier' => 4, 'unlockedmodules' => ['glossary']]));

        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $renderer = $PAGE->get_renderer('core');
        $first = messages::render_for_page($course, $renderer);
        $quiz = get_string('modulename', 'quiz');
        $choice = get_string('modulename', 'choice');
        $list = get_string('unlock_list', 'tool_wizards', (object) ['first' => $quiz, 'last' => $choice]);
        $this->assertStringContainsString(s(get_string('unlock_message', 'tool_wizards', $list)), $first);
        $this->assertStringContainsString('data-type="quiz"', $first, 'Offers the quiz mini-wizard.');

        set_user_preference(local\prompt::PREF_HIDE, 1);
        $second = messages::render_for_page($course, $renderer);
        $this->assertStringContainsString(
            s(get_string('unlock_message_one', 'tool_wizards', get_string('modulename', 'glossary'))),
            $second,
            'The next one, next time, worded for one activity.'
        );
        $this->assertStringNotContainsString('data-action="add"', $second, 'No mini-wizard with suggestions off.');

        $this->assertSame('', messages::render_for_page($course, $renderer), 'Each is shown once.');
    }

    /**
     * No "try it" button on a course where the teacher hid the suggestions: the confirmation after
     * adding would not be shown there.
     */
    public function test_no_try_on_dismissed_course(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        local\prompt::dismiss_course($course->id);
        observer::tier_unlocked($this->unlock_event($teacher->id, ['tier' => 3, 'unlockedmodules' => ['quiz']]));
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $html = messages::render_for_page($course, $PAGE->get_renderer('core'));
        $this->assertStringContainsString(get_string('modulename', 'quiz'), $html);
        $this->assertStringNotContainsString('data-action="add"', $html);
    }

    /**
     * With the real Teacher scaffold installed, triggering its event queues the message
     * (only runs where that plugin is present, such as the development site).
     */
    public function test_real_teacherscaffold_event(): void {
        global $DB;
        if (!class_exists('\\tool_teacherscaffold\\event\\tier_unlocked')) {
            $this->markTestSkipped('tool_teacherscaffold is not installed.');
        }
        $this->resetAfterTest();
        // The observer is not internal, so it runs only once no transaction is open. On PostgreSQL
        // every test runs inside one (lib/phpunit/classes/advanced_testcase.php:66-69) unless this is set.
        $this->preventResetByRollback();
        $teacher = $this->getDataGenerator()->create_user();
        $event = \tool_teacherscaffold\event\tier_unlocked::create([
            'context' => \context_system::instance(),
            'relateduserid' => $teacher->id,
            'other' => ['tier' => 2, 'unlockedmodules' => ['glossary', 'url']],
        ]);
        $event->trigger();
        $row = $DB->get_record('tool_wizards_message', ['userid' => $teacher->id], '*', MUST_EXIST);
        $this->assertSame(['tier' => 2, 'modules' => ['glossary', 'url']], json_decode($row->payload, true));
    }

    /**
     * Rows go with their course or user.
     */
    public function test_cleanup(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        local\prompt::dismiss_course($course->id);
        messages::queue($user->id, messages::TYPE_UNLOCK, ['tier' => 2, 'modules' => ['quiz']]);

        delete_course($course, false);
        $this->assertSame(0, $DB->count_records('tool_wizards_dismissed', ['courseid' => $course->id]));
        $this->assertSame(1, $DB->count_records('tool_wizards_message', ['userid' => $user->id]));

        delete_user($user);
        $this->assertSame(0, $DB->count_records('tool_wizards_message', ['userid' => $user->id]));
    }
}
