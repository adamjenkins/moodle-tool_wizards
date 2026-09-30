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

namespace tool_wizards\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use tool_wizards\local\messages;
use tool_wizards\local\prompt;

/**
 * Tests for the privacy provider.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \stdClass a user with data */
    protected \stdClass $user;

    /** @var \stdClass another user with data */
    protected \stdClass $other;

    /** @var \stdClass a course */
    protected \stdClass $course;

    /**
     * Two users, each with a hidden-suggestions course, a queued message and the preference.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        foreach ([$this->user, $this->other] as $user) {
            $this->setUser($user);
            prompt::dismiss_course($this->course->id);
            messages::queue($user->id, messages::TYPE_UNLOCK, ['tier' => 2, 'modules' => ['quiz']]);
            set_user_preference(prompt::PREF_HIDE, 1, $user);
        }
        $this->setUser(null);
    }

    /**
     * Every table and preference is described.
     */
    public function test_metadata(): void {
        $items = provider::get_metadata(new \core_privacy\local\metadata\collection('tool_wizards'))->get_collection();
        $names = array_map(fn($i) => $i->get_name(), $items);
        $this->assertEqualsCanonicalizing(
            ['tool_wizards_dismissed', 'tool_wizards_message', 'tool_wizards_hidesuggestions'],
            $names
        );
    }

    /**
     * The user's own context, and only it, holds their data.
     */
    public function test_contexts_and_users(): void {
        $contexts = provider::get_contexts_for_userid($this->user->id)->get_contextids();
        $this->assertEquals([\context_user::instance($this->user->id)->id], array_values($contexts));

        $userlist = new userlist(\context_user::instance($this->user->id), 'tool_wizards');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->user->id], $userlist->get_userids());

        $userlist = new userlist(\context_course::instance($this->course->id), 'tool_wizards');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * Export writes the dismissals, messages and preference.
     */
    public function test_export(): void {
        $context = \context_user::instance($this->user->id);
        $this->export_context_data_for_user($this->user->id, $context, 'tool_wizards');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $plugin = get_string('pluginname', 'tool_wizards');
        $dismissed = $writer->get_data([$plugin, get_string('privacy:path:dismissed', 'tool_wizards')]);
        $this->assertEquals($this->course->id, $dismissed->courses[0]->courseid);
        $queued = $writer->get_data([$plugin, get_string('privacy:path:messages', 'tool_wizards')]);
        $this->assertSame(messages::TYPE_UNLOCK, $queued->messages[0]->type);

        provider::export_user_preferences($this->user->id);
        $prefs = writer::with_context(\context_system::instance())->get_user_preferences('tool_wizards');
        $this->assertSame('1', $prefs->tool_wizards_hidesuggestions->value);
    }

    /**
     * Deleting for one user leaves the other's data alone.
     */
    public function test_delete_for_user(): void {
        global $DB;
        $context = \context_user::instance($this->user->id);
        provider::delete_data_for_user(new approved_contextlist($this->user, 'tool_wizards', [$context->id]));
        $this->assertSame(0, $DB->count_records('tool_wizards_dismissed', ['userid' => $this->user->id]));
        $this->assertSame(0, $DB->count_records('tool_wizards_message', ['userid' => $this->user->id]));
        $this->assertSame(1, $DB->count_records('tool_wizards_dismissed', ['userid' => $this->other->id]));
        $this->assertSame(1, $DB->count_records('tool_wizards_message', ['userid' => $this->other->id]));
    }

    /**
     * Deleting a user list in a user context, and all data in a context.
     */
    public function test_delete_for_users_and_context(): void {
        global $DB;
        $context = \context_user::instance($this->user->id);
        provider::delete_data_for_users(new approved_userlist($context, 'tool_wizards', [$this->user->id]));
        $this->assertSame(0, $DB->count_records('tool_wizards_message', ['userid' => $this->user->id]));

        provider::delete_data_for_all_users_in_context(\context_user::instance($this->other->id));
        $this->assertSame(0, $DB->count_records('tool_wizards_dismissed'));
        $this->assertSame(0, $DB->count_records('tool_wizards_message'));

        // A course context holds nothing of this plugin's, so nothing is touched there.
        messages::queue($this->other->id, messages::TYPE_UNLOCK, ['tier' => 2, 'modules' => ['quiz']]);
        provider::delete_data_for_all_users_in_context(\context_course::instance($this->course->id));
        $this->assertSame(1, $DB->count_records('tool_wizards_message'));
    }
}
