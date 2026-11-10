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
use tool_wizards\local\wizard\packager;
use tool_wizards\local\wizard\repository;

/**
 * Tests for the plugin's lib.php callbacks.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('tool_wizards_pluginfile')]
final class lib_test extends advanced_testcase {
    /**
     * Pictures of draft or disabled wizards are not served to people who do not manage wizards.
     *
     * Only the refusals are tested: serving a file sends it and ends the script.
     */
    public function test_pluginfile_refuses_unpublished_wizards(): void {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/wizards/lib.php');
        $this->resetAfterTest();
        $forum = repository::get_by_key('forum');
        $id = (int) $forum->id;
        packager::store_pictures($id, ['talk.png' => $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png'], true);
        $context = \core\context\system::instance();
        $this->assertTrue(get_file_storage()->file_exists($context->id, 'tool_wizards', 'picture', $id, '/', 'talk.png'));

        $this->setUser($this->getDataGenerator()->create_user());
        $serve = fn(int $itemid) => tool_wizards_pluginfile(null, null, $context, 'picture', [$itemid, 'talk.png'], true);

        repository::set_status($id, repository::STATUS_DRAFT);
        $this->assertFalse($serve($id));
        repository::set_status($id, repository::STATUS_DISABLED);
        $this->assertFalse($serve($id));
        $this->assertFalse($serve(-1), 'No such wizard.');
        $this->assertFalse(tool_wizards_pluginfile(null, null, $context, 'other', [$id, 'talk.png'], true));
    }
}
