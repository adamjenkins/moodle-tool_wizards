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

namespace tool_wizards\form;

use advanced_testcase;
use core_form\external\dynamic_form as dynamic_form_ws;
use tool_wizards\local\wizard\defaults;
use tool_wizards\local\wizard\engine;

/**
 * Every activity wizard creates its activity, for every one of its purposes, with every question
 * answered as a teacher would leave it: the default where there is one, the first choice, sample
 * text, and a real file where one is needed.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(wizard_form::class)]
final class every_purpose_test extends advanced_testcase {
    /** @var array Files for the wizards that need one, relative to $CFG->dirroot. */
    const FILES = [
        'file' => 'mod/assign/feedback/editpdf/tests/fixtures/submission.pdf',
        'slides' => 'mod/assign/feedback/editpdf/tests/fixtures/submission.pdf',
        'folder' => 'mod/assign/feedback/editpdf/tests/fixtures/submission.pdf',
        'picture' => 'lib/tests/fixtures/gd-logo.png',
        'h5p' => 'h5p/tests/fixtures/filltheblanks.h5p',
        'scorm' => 'mod/scorm/tests/packages/singlescobasic.zip',
        'contentpackage' => 'mod/imscp/tests/packages/singlescobasic.zip',
    ];

    /** @var string[] Wizards left out: BigBlueButton asks its meeting server about the site while its form is built. */
    const SKIP = ['bigbluebutton'];

    /**
     * Create the activity with each purpose.
     *
     * @param string $key the wizard
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('wizard_provider')]
    public function test_every_purpose(string $key): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2, 'enablecompletion' => 1]);
        $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types(['name' => 'A tool',
            'baseurl' => 'https://example.com/lti', 'coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
            'state' => LTI_TOOL_STATE_CONFIGURED]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $_POST['sesskey'] = sesskey();

        $doc = defaults::shipped()[$key];
        $engine = new engine($doc, 0, $course);
        $questions = $engine->questions();
        $purpose = null;
        foreach ($questions as $qkey => $item) {
            if ($item['kind'] === 'cards' && empty($item['shared'])) {
                $purpose = $qkey;
                break;
            }
        }
        $values = $purpose ? array_column($questions[$purpose]['choices'], 'value') : [null];
        $this->assertNotEmpty($values, "$key offers no purposes here");
        $modname = $doc['target']['modname'];
        foreach ($values as $i => $value) {
            $name = "$key " . ($value ?? 'only');
            $data = $this->answers($engine, $questions, $key, $name);
            if ($purpose) {
                $data[$purpose] = $value;
            }
            $data += ['courseid' => $course->id, 'wizard' => $key, 'sesskey' => sesskey(),
                '_qf__tool_wizards_form_wizard_form' => 1];
            $before = $DB->count_records('course_modules', ['course' => $course->id]);
            $result = dynamic_form_ws::execute(wizard_form::class, http_build_query($data));
            $this->assertTrue($result['submitted'], "$name: " . trim(strip_tags($result['html'] ?? '')));
            $this->assertEquals($before + 1, $DB->count_records('course_modules', ['course' => $course->id]), $name);
            $cmid = json_decode($result['data'])->cmid;
            $this->assertEquals($modname, get_fast_modinfo($course->id)->get_cm($cmid)->modname, $name);
        }
    }

    /**
     * Answers for every question: its default, its first choice, sample text, or a file.
     *
     * @param engine $engine the engine
     * @param array $questions the questions
     * @param string $key the wizard
     * @param string $name the name to give
     * @return array form data
     */
    protected function answers(engine $engine, array $questions, string $key, string $name): array {
        global $CFG, $USER;
        $data = [];
        foreach ($questions as $qkey => $item) {
            $default = $engine->default_for($item);
            switch ($item['kind']) {
                case 'text':
                case 'textarea':
                    $data[$qkey] = in_array($qkey, ['name', 'concept', 'title'], true) ? $name
                        : (str_contains($qkey, 'url') ? 'https://example.com/page' : 'Sample ' . $qkey);
                    break;
                case 'editor':
                    $data[$qkey] = ['text' => '<p>Sample ' . $qkey . '</p>', 'format' => FORMAT_HTML];
                    break;
                case 'number':
                case 'percent':
                    $data[$qkey] = $default !== null && $default !== '' ? $default : ($item['min'] ?? 1);
                    break;
                case 'yesno':
                    $data[$qkey] = (int) $default;
                    break;
                case 'select':
                case 'choice':
                case 'cards':
                    $choices = array_column($item['choices'] ?? [], 'value');
                    if ($choices) {
                        $data[$qkey] = $default !== null && in_array((string) $default, array_map('strval', $choices), true)
                            ? $default : $choices[0];
                    }
                    break;
                case 'file':
                    $draftid = file_get_unused_draft_itemid();
                    get_file_storage()->create_file_from_pathname([
                        'contextid' => \core\context\user::instance($USER->id)->id, 'component' => 'user',
                        'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
                        'filename' => basename(self::FILES[$key]),
                    ], $CFG->dirroot . '/' . self::FILES[$key]);
                    $data[$qkey] = $draftid;
                    break;
            }
        }
        return $data;
    }

    /**
     * Every shipped activity wizard.
     *
     * @return array
     */
    public static function wizard_provider(): array {
        $out = [];
        foreach (glob(__DIR__ . '/../../defaults/*.json') as $file) {
            $doc = json_decode(file_get_contents($file), true);
            if (($doc['target']['type'] ?? '') === 'module' && !in_array($doc['key'], self::SKIP, true)) {
                $out[$doc['key']] = [$doc['key']];
            }
        }
        ksort($out);
        return $out;
    }
}
