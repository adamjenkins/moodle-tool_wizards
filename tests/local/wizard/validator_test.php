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
 * Tests for the definition validator, and the shipped defaults against it.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(validator::class)]
final class validator_test extends advanced_testcase {
    /**
     * A small valid activity wizard.
     *
     * @return array
     */
    public static function minimal(): array {
        return [
            'format' => validator::FORMAT,
            'key' => 'mini',
            'target' => ['type' => 'module', 'modname' => 'forum'],
            'title' => ['en' => 'Mini'],
            'screens' => [[
                'key' => 'basics', 'title' => 'Basics',
                'items' => [
                    ['key' => 'name', 'kind' => 'text', 'label' => ['en' => 'Name'], 'required' => true,
                        'sets' => ['field' => 'name']],
                    ['key' => 'kind', 'kind' => 'cards', 'label' => 'Kind', 'choices' => [
                        ['value' => 'a', 'title' => 'A', 'sets' => ['type' => 'general'], 'preset' => ['subs' => 2]],
                        ['value' => 'b', 'title' => 'B', 'sets' => ['type' => 'qanda']]]],
                ],
            ], [
                'key' => 'emails', 'title' => 'Emails', 'when' => ['answer' => 'kind', 'is' => 'a'],
                'items' => [['key' => 'subs', 'kind' => 'select', 'label' => 'Who', 'sets' => ['field' => 'forcesubscribe'],
                    'choices' => [['value' => 0, 'title' => 'Optional'], ['value' => 2, 'title' => 'Auto']]]],
            ], ['use' => 'shared:completion', 'choices' => [['value' => 'posts', 'title' => 'Posts',
                'sets' => ['completionpostsenabled' => 1]]]]],
        ];
    }

    /**
     * Every shipped default is valid.
     */
    public function test_defaults_are_valid(): void {
        $this->resetAfterTest();
        $shipped = defaults::shipped();
        $this->assertSame(
            ['course', 'file', 'slides', 'picture', 'page', 'forum', 'glossary', 'quiz', 'assignment', 'link',
            'folder', 'book', 'choice', 'feedback', 'database', 'wiki', 'lesson', 'workshop', 'h5p', 'scorm',
            'contentpackage', 'externaltool', 'bigbluebutton', 'subsection', 'questionbank'],
            array_slice(array_keys($shipped), 0, 25)
        );
        // Then the in-activity wizards, one for each built-in content handler.
        $handlers = [];
        foreach (array_slice($shipped, 25) as $doc) {
            $this->assertSame('content', $doc['target']['type']);
            $handlers[] = $doc['target']['content'];
        }
        $this->assertEqualsCanonicalizing(array_keys(extensions::contents()), $handlers);
        foreach ($shipped as $key => $doc) {
            $this->assertSame([], validator::check($doc), $key);
        }
    }

    /**
     * A minimal wizard is valid.
     */
    public function test_minimal_valid(): void {
        $this->resetAfterTest();
        $this->assertSame([], validator::check(self::minimal()));
    }

    /**
     * Problems are reported with their path, and unknown things are errors.
     *
     * @param callable $break changes the minimal wizard
     * @param string $expected the start of the expected problem
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('broken_provider')]
    public function test_problems_reported(callable $break, string $expected): void {
        $this->resetAfterTest();
        $doc = self::minimal();
        $break($doc);
        $problems = validator::check($doc);
        $this->assertNotEmpty(
            array_filter($problems, fn($p) => str_starts_with($p, $expected)),
            'Expected "' . $expected . '..." in: ' . implode(' | ', $problems)
        );
    }

    /**
     * Broken documents.
     *
     * @return array
     */
    public static function broken_provider(): array {
        return [
            'format' => [function (&$d) {
                $d['format'] = 'x';
            }, 'format:'],
            'key' => [function (&$d) {
                $d['key'] = 'Bad Key';
            }, 'key:'],
            'module' => [function (&$d) {
                $d['target']['modname'] = 'nosuchmodule';
            }, 'target.modname:'],
            'unknown property' => [function (&$d) {
                $d['screens'][0]['items'][0]['colour'] = 'red';
            }, 'screens[0].items[0].colour:'],
            'unknown kind' => [function (&$d) {
                $d['screens'][0]['items'][0]['kind'] = 'slider';
            }, 'screens[0].items[0].kind:'],
            'bad language' => [function (&$d) {
                $d['title'] = ['English' => 'Mini'];
            }, 'title.English:'],
            'missing string' => [function (&$d) {
                $d['title'] = ['string' => 'nosuchstring_xyz'];
            }, 'title.string:'],
            'duplicate question' => [function (&$d) {
                $d['screens'][1]['items'][0]['key'] = 'name';
            }, 'screens[1].items[0].key:'],
            'duplicate choice' => [function (&$d) {
                $d['screens'][0]['items'][1]['choices'][1]['value'] = 'a';
            }, 'screens[0].items[1].choices[1].value:'],
            'condition on unknown answer' => [function (&$d) {
                $d['screens'][1]['when'] = ['answer' => 'nothing', 'is' => 1];
            }, 'screens[1].when.answer:'],
            'unknown condition' => [function (&$d) {
                $d['screens'][1]['when'] = ['weather' => 'sunny'];
            }, 'screens[1].when:'],
            'preset to unknown question' => [function (&$d) {
                $d['screens'][0]['items'][1]['choices'][0]['preset'] = ['nothing' => 1];
            }, 'screens[0].items[1].choices[0].preset.nothing:'],
            'preset value not a choice' => [function (&$d) {
                $d['screens'][0]['items'][1]['choices'][0]['preset'] = ['subs' => 9];
            }, 'screens[0].items[1].choices[0].preset.subs:'],
            'bad field' => [function (&$d) {
                $d['screens'][0]['items'][0]['sets'] = ['field' => 'na me'];
            }, 'screens[0].items[0].sets.field:'],
            'unknown transform' => [function (&$d) {
                $d['screens'][0]['items'][0]['sets'] = ['transform' => 'x/y'];
            }, 'screens[0].items[0].sets.transform:'],
            'unknown action' => [function (&$d) {
                $d['actions'] = [['action' => 'tool_wizards/nothing']];
            }, 'actions[0].action:'],
            'course kind in activity wizard' => [function (&$d) {
                $d['screens'][0]['items'][0]['kind'] = 'shortname';
            }, 'screens[0].items[0].kind:'],
            'shared screen in course wizard' => [function (&$d) {
                $d['target'] = ['type' => 'course'];
            }, 'screens[2].use:'],
            'bad picture' => [function (&$d) {
                $d['screens'][0]['items'][1]['choices'][0]['picture'] = 'http://example.com/x.png';
            }, 'screens[0].items[1].choices[0].picture:'],
            'unknown capability' => [function (&$d) {
                $d['screens'][1]['when'] = ['capability' => 'moodle/no:suchthing'];
            }, 'screens[1].when.capability:'],
        ];
    }

    /**
     * An in-activity wizard names a registered content handler for its activity, sets only that
     * handler's fields, and has no shared screens.
     */
    public function test_content_target(): void {
        $this->resetAfterTest();
        $doc = [
            'format' => validator::FORMAT,
            'key' => 'moreoptions',
            'target' => ['type' => 'content', 'modname' => 'choice', 'content' => 'tool_wizards/choice_option'],
            'title' => 'More options',
            'screens' => [['key' => 'basics', 'title' => 'Basics', 'items' => [
                ['key' => 'x', 'kind' => 'text', 'label' => 'X', 'sets' => ['field' => 'nosuchfield']],
            ]]],
        ];
        $problems = validator::check($doc);
        $this->assertNotEmpty(array_filter($problems, fn($p) => str_starts_with($p, 'screens[0].items[0].sets.field:')));

        $wrong = $doc;
        $wrong['target']['modname'] = 'forum';
        $this->assertNotEmpty(array_filter(validator::check($wrong), fn($p) => str_starts_with($p, 'target.content:')));

        $unknown = $doc;
        $unknown['target']['content'] = 'tool_wizards/nothing';
        $this->assertNotEmpty(array_filter(validator::check($unknown), fn($p) => str_starts_with($p, 'target.content:')));

        $shared = $doc;
        $shared['screens'][] = ['use' => 'shared:visibility'];
        $this->assertNotEmpty(array_filter(validator::check($shared), fn($p) => str_starts_with($p, 'screens[1].use:')));
    }

    /**
     * JSON that does not parse is reported, not thrown.
     */
    public function test_bad_json(): void {
        $this->resetAfterTest();
        $problems = validator::check_json('{"format": ');
        $this->assertCount(1, $problems);
        $this->assertStringStartsWith('(document): not valid JSON', $problems[0]);
    }
}
