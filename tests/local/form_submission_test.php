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
use tool_wizards\fixtures\dependent_form;

/**
 * Tests for the browser emulation that submits answers to core forms.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(form_submission::class)]
final class form_submission_test extends advanced_testcase {
    /**
     * Load the fixture form.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/admin/tool/wizards/tests/fixtures/dependent_form.php');
    }

    /**
     * A page for the forms to render on.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/tool/wizards/tests'));
    }

    /**
     * Untouched, the form posts its defaults minus what its rules disable or hide.
     */
    public function test_untouched(): void {
        $values = form_submission::browser_values(new dependent_form());
        $this->assertSame('0', $values['limitenabled']);
        $this->assertArrayNotHasKey('limit', $values, 'Disabled while the limit is off.');
        $this->assertSame('a', $values['mode']);
        $this->assertSame('x', $values['extra']);
        $this->assertSame('one', $values['kind']);
        $this->assertSame(['p'], $values['tags']);
    }

    /**
     * Answers are applied before the rules are judged, as a browser judges them after a click:
     * switching the limit on sends its default value, and choosing mode b stops sending "extra".
     */
    public function test_rules_judged_on_answers(): void {
        $values = form_submission::browser_values(new dependent_form(), ['limitenabled' => 1, 'mode' => 'b']);
        $this->assertSame('1', $values['limitenabled']);
        $this->assertSame('10', $values['limit'] ?? null, 'The limit is enabled, so its default is sent.');
        $this->assertSame('b', $values['mode']);
        $this->assertArrayNotHasKey('extra', $values, 'Hidden in mode b, so not sent.');
    }

    /**
     * An answer for a field the answers themselves disable is dropped, as a browser would drop it.
     */
    public function test_answer_for_disabled_field_dropped(): void {
        $values = form_submission::browser_values(new dependent_form(), ['limitenabled' => 0, 'limit' => 5]);
        $this->assertArrayNotHasKey('limit', $values);
        $values = form_submission::browser_values(new dependent_form(), ['limitenabled' => 1, 'limit' => 5]);
        $this->assertSame('5', $values['limit']);
    }

    /**
     * Radios, multiple selects, editors, and names the form does not render.
     */
    public function test_control_kinds(): void {
        $values = form_submission::browser_values(new dependent_form(), [
            'kind' => 'two',
            'tags' => ['q', 'r'],
            'intro' => ['text' => '<p>Hi</p>', 'format' => FORMAT_HTML],
            'notrendered' => 'kept',
        ]);
        $this->assertSame('two', $values['kind']);
        $this->assertSame(['q', 'r'], $values['tags']);
        $this->assertSame('<p>Hi</p>', $values['intro']['text']);
        $this->assertSame((string) FORMAT_HTML, (string) $values['intro']['format']);
        $this->assertSame('kept', $values['notrendered']);
    }
}
