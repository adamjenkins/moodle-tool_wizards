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

namespace tool_wizards\local\wizard;

use advanced_testcase;

/**
 * Tests for the default wizards and keeping them up to date.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(defaults::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(repository::class)]
final class defaults_test extends advanced_testcase {
    /**
     * The install put every default in place, enabled, unedited.
     */
    public function test_installed(): void {
        $this->resetAfterTest();
        $keys = array_values(array_map(fn($r) => $r->wizardkey, repository::all()));
        $this->assertSame(array_keys(defaults::shipped()), $keys);
        foreach (repository::all() as $record) {
            $this->assertEquals(repository::STATUS_ENABLED, $record->status, $record->wizardkey);
            $this->assertSame(repository::ORIGIN_DEFAULT, $record->origin);
            $this->assertFalse(repository::is_edited($record), $record->wizardkey);
        }
    }

    /**
     * An unedited default follows a newer shipped version; an edited one is flagged, and can be
     * reset or kept.
     */
    public function test_newer_versions(): void {
        global $DB;
        $this->resetAfterTest();
        $forum = repository::get_by_key('forum');
        $quiz = repository::get_by_key('quiz');

        // The site edits the quiz wizard.
        $doc = repository::definition($quiz);
        $doc['title'] = ['en' => 'Our quiz'];
        repository::save($doc, [], (int) $quiz->id);
        $this->assertTrue(repository::is_edited(repository::get((int) $quiz->id)));

        // Pretend the site had older versions installed, so the shipped ones are newer.
        $DB->set_field(repository::TABLE, 'shippedversion', 0, ['wizardkey' => 'forum']);
        $DB->set_field(repository::TABLE, 'shippedversion', 0, ['wizardkey' => 'quiz']);
        $DB->set_field(
            repository::TABLE,
            'definition',
            repository::encode(['old' => 1] + repository::definition($forum)),
            ['wizardkey' => 'forum']
        );
        $DB->set_field(
            repository::TABLE,
            'shippedhash',
            repository::hash(['old' => 1] + repository::definition($forum)),
            ['wizardkey' => 'forum']
        );
        repository::changed();
        defaults::sync();

        $forum = repository::get((int) $forum->id);
        $this->assertArrayNotHasKey('old', repository::definition($forum), 'The unedited default was updated.');
        $this->assertEquals(0, $forum->newerversion);
        $quiz = repository::get((int) $quiz->id);
        $this->assertSame(['en' => 'Our quiz'], repository::definition($quiz)['title'], 'The edited one is left alone.');
        $this->assertEquals(1, $quiz->newerversion, 'and flagged.');
        $this->assertNotEmpty(defaults::compare(repository::definition($quiz), defaults::shipped()['quiz']));

        defaults::keep_mine((int) $quiz->id);
        $this->assertEquals(0, repository::get((int) $quiz->id)->newerversion);

        defaults::reset((int) $quiz->id);
        $quiz = repository::get((int) $quiz->id);
        $this->assertSame(defaults::shipped()['quiz'], repository::definition($quiz));
        $this->assertFalse(repository::is_edited($quiz));
    }

    /**
     * Status and order are not edits; defaults cannot be deleted, custom wizards can.
     */
    public function test_status_order_delete(): void {
        $this->resetAfterTest();
        $forum = repository::get_by_key('forum');
        repository::set_status((int) $forum->id, repository::STATUS_DISABLED);
        repository::move((int) $forum->id, -1);
        $forum = repository::get((int) $forum->id);
        $this->assertFalse(repository::is_edited($forum));
        $this->assertFalse(repository::delete((int) $forum->id));

        $copy = repository::duplicate((int) $forum->id);
        $record = repository::get($copy);
        $this->assertSame('forum_copy', $record->wizardkey);
        $this->assertEquals(repository::STATUS_DRAFT, $record->status);
        $this->assertTrue(repository::delete($copy));
        $this->assertNull(repository::get($copy));
    }
}
