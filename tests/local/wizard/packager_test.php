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
 * Tests for exporting and importing wizards.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(packager::class)]
final class packager_test extends advanced_testcase {
    /**
     * An exported zip holds the wizards, their pictures, the runbook and the schema, and imports back.
     */
    public function test_round_trip(): void {
        global $CFG;
        $this->resetAfterTest();
        $forum = repository::get_by_key('forum');
        $doc = repository::definition($forum);
        $doc['screens'][0]['items'][3]['choices'][0]['picture'] = 'file:talk.png';
        repository::save($doc, [], (int) $forum->id);
        packager::store_pictures((int) $forum->id, ['talk.png' => $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png'], true);

        $zip = packager::export([(int) $forum->id, (int) repository::get_by_key('quiz')->id]);
        $files = (new \zip_packer())->list_files($zip);
        $names = array_map(fn($f) => $f->pathname, $files);
        foreach (
            ['forum/wizard.json', 'forum/pictures/talk.png', 'quiz/wizard.json', 'manifest.json', 'RUNBOOK.md',
                'wizard.schema.json'] as $name
        ) {
            $this->assertContains($name, $names);
        }

        // Import as copies: new keys, drafts, pictures with them.
        $results = packager::import($zip, 'wizards.zip', packager::COPY);
        $this->assertSame(['copied', 'copied'], array_column($results, 'result'));
        $copy = repository::get_by_key('forum_copy');
        $this->assertEquals(repository::STATUS_DRAFT, $copy->status);
        $this->assertSame(repository::ORIGIN_IMPORTED, $copy->origin);
        $this->assertTrue((bool) get_file_storage()->get_file(
            \context_system::instance()->id,
            'tool_wizards',
            'picture',
            $copy->id,
            '/',
            'talk.png'
        ));

        // Skip, then replace.
        $this->assertSame(['skipped', 'skipped'], array_column(packager::import($zip, 'wizards.zip', packager::SKIP), 'result'));
        $this->assertSame(['replaced', 'replaced'], array_column(
            packager::import($zip, 'wizards.zip', packager::REPLACE),
            'result'
        ));
        $this->assertSame(repository::ORIGIN_DEFAULT, repository::get_by_key('forum')->origin, 'A replaced default stays one.');
    }

    /**
     * A bad wizard is reported with its problems and nothing is saved for it.
     */
    public function test_invalid_not_imported(): void {
        $this->resetAfterTest();
        $path = make_request_directory() . '/bad.json';
        $doc = validator_test::minimal();
        $doc['key'] = 'brandnew';
        $doc['screens'][0]['items'][0]['kind'] = 'slider';
        file_put_contents($path, json_encode($doc));
        $results = packager::import($path, 'bad.json', packager::COPY);
        $this->assertSame('invalid', $results[0]['result']);
        $this->assertNotEmpty($results[0]['problems']);
        $this->assertNull(repository::get_by_key('brandnew'));

        // A file: picture that is not in the zip is a problem too.
        $doc['screens'][0]['items'][0]['kind'] = 'text';
        $doc['screens'][0]['items'][1]['choices'][0]['picture'] = 'file:missing.png';
        file_put_contents($path, json_encode($doc));
        $results = packager::import($path, 'bad.json', packager::COPY);
        $this->assertSame('invalid', $results[0]['result']);
        $this->assertStringContainsString('missing.png', implode(' ', $results[0]['problems']));
    }
}
