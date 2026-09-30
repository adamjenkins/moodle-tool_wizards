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

use core_external\external_api;

/**
 * Tests for the short name web services.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(suggest_shortname::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(check_shortname::class)]
final class shortname_test extends \advanced_testcase {
    /**
     * A course creator gets suggestions and checks.
     */
    public function test_creator_can_use_both(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $user = $this->getDataGenerator()->create_user();
        role_assign(
            $DB->get_field('role', 'id', ['shortname' => 'coursecreator']),
            $user->id,
            \context_coursecat::instance($category->id)->id
        );
        $this->setUser($user);
        $this->getDataGenerator()->create_course(['shortname' => 'BIO-1']);

        $result = external_api::clean_returnvalue(
            suggest_shortname::execute_returns(),
            suggest_shortname::execute('Basic Chemistry')
        );
        $this->assertSame('BC-' . userdate(time(), '%Y'), $result['shortname']);

        $taken = external_api::clean_returnvalue(check_shortname::execute_returns(), check_shortname::execute('BIO-1'));
        $this->assertFalse($taken['available']);
        $this->assertSame('BIO-1 2', $taken['suggestion']);

        $free = external_api::clean_returnvalue(check_shortname::execute_returns(), check_shortname::execute('BIO-9'));
        $this->assertTrue($free['available']);
    }

    /**
     * Someone who cannot create courses anywhere may not probe short names.
     */
    public function test_non_creator_refused(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        check_shortname::execute('ANY');
    }
}
