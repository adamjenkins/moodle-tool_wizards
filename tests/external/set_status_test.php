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

namespace tool_wizards\external;

use advanced_testcase;
use tool_wizards\local\wizard\repository;

/**
 * Tests for switching wizards on and off from the wizard list.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(set_status::class)]
final class set_status_test extends advanced_testcase {
    /**
     * A group is switched off and on again; a draft in it is left alone.
     */
    public function test_group(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $forum = (int) repository::get_by_key('forum')->id;
        $quiz = (int) repository::get_by_key('quiz')->id;
        $page = (int) repository::get_by_key('page')->id;
        repository::set_status($page, repository::STATUS_DRAFT);

        $result = set_status::execute([$forum, $quiz, $page], false);
        $this->assertEquals([
            ['id' => $forum, 'status' => repository::STATUS_DISABLED],
            ['id' => $quiz, 'status' => repository::STATUS_DISABLED],
            ['id' => $page, 'status' => repository::STATUS_DRAFT],
        ], $result);
        $this->assertEquals(repository::STATUS_DISABLED, repository::get($forum)->status);

        set_status::execute([$forum], true);
        $this->assertEquals(repository::STATUS_ENABLED, repository::get($forum)->status);
        $this->assertEquals(repository::STATUS_DISABLED, repository::get($quiz)->status);
        $this->assertEquals(repository::STATUS_DRAFT, repository::get($page)->status);
    }

    /**
     * Only wizard managers may switch them.
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        set_status::execute([(int) repository::get_by_key('forum')->id], false);
    }
}
