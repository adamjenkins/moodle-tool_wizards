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
use core_form\external\dynamic_form as dynamic_form_ws;

/**
 * Who is offered the wizards: only people who can add content to the course (editing teachers and
 * managers by default), never students or non-editing teachers. Every place a wizard is offered or
 * opened is checked for each role.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_wizards\local\prompt::class)]
final class access_test extends advanced_testcase {
    /**
     * Each entry point, for each role.
     *
     * @param string $role the course role
     * @param bool $offered whether that role should be offered the wizards
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('role_provider')]
    public function test_entry_points(string $role, bool $offered): void {
        global $CFG, $PAGE, $USER, $DB;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, $role);
        $this->setUser($user);
        $_POST['sesskey'] = sesskey();
        $modinfo = get_fast_modinfo($course);

        $footer = function (string $url, array $params, ?\cm_info $cm = null, string $pagetype = '') use ($course): string {
            global $PAGE;
            $PAGE = new \moodle_page();
            $PAGE->set_url(new \moodle_url($url, $params));
            if ($cm) {
                $PAGE->set_cm($cm, $course);
            } else {
                $PAGE->set_course($course);
                $PAGE->set_context(\core\context\course::instance($course->id));
            }
            if ($pagetype !== '') {
                $PAGE->set_pagetype($pagetype);
            }
            $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
            hook_callbacks::before_footer($hook);
            return $hook->get_output();
        };

        // The first-content card on the course page.
        $PAGE = new \moodle_page();
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $PAGE->set_course($course);
        $PAGE->set_context(\core\context\course::instance($course->id));
        $card = \tool_wizards\local\prompt::render_for_page($PAGE, $PAGE->get_renderer('core'));
        $this->assertSame($offered, str_contains($card, 'tool_wizards-firstcontent'), "$role: first-content card");

        // The section links ("Add with a wizard"), in edit mode.
        $USER->editing = 1;
        $html = $footer('/course/view.php', ['id' => $course->id]);
        $this->assertSame($offered, str_contains($html, 'tool_wizards-wizardlist'), "$role: section links");
        $USER->editing = 0;

        // The wand button on the quiz's questions page, the question bank, and the question banks page.
        $html = $footer('/mod/quiz/edit.php', ['cmid' => $quiz->cmid], $modinfo->get_cm($quiz->cmid), 'mod-quiz-edit');
        $this->assertSame($offered, str_contains($html, 'tool_wizards-contentlist'), "$role: quiz page");
        $html = $footer('/question/edit.php', ['cmid' => $qbank->cmid], $modinfo->get_cm($qbank->cmid), 'question-edit');
        $this->assertSame($offered, str_contains($html, 'tool_wizards-contentlist'), "$role: question bank page");
        $html = $footer('/question/banks.php', ['courseid' => $course->id]);
        $this->assertSame($offered, str_contains($html, 'tool_wizards-contentlist'), "$role: question banks page");

        // The footer help menu's "Bring back the course wizards", after hiding them.
        set_user_preference(\tool_wizards\local\prompt::PREF_HIDE, 1);
        $this->assertSame($offered, \tool_wizards\local\prompt::may_bring_back($course), "$role: bring back");
        set_user_preference(\tool_wizards\local\prompt::PREF_HIDE, 0);

        // Opening the wizards directly, as the browser would.
        $opens = [
            [\tool_wizards\form\wizard_form::class, 'wizard=forum&courseid=' . $course->id],
            [\tool_wizards\form\wizard_form::class, 'wizard=questionbank&courseid=' . $course->id],
            [\tool_wizards\form\content_wizard_form::class, 'wizard=quizquestion&cmid=' . $quiz->cmid],
            [\tool_wizards\form\content_wizard_form::class, 'wizard=qbankcategory&cmid=' . $qbank->cmid],
            [\tool_wizards\form\content_wizard_form::class, 'wizard=qbankquestion&cmid=' . $qbank->cmid],
        ];
        foreach ($opens as [$class, $args]) {
            try {
                $result = dynamic_form_ws::execute($class, $args);
                $this->assertTrue($offered, "$role opened $args");
                $this->assertStringContainsString('tool_wizards-step', $result['html']);
            } catch (\moodle_exception $e) {
                $this->assertFalse($offered, "$role could not open $args: " . $e->getMessage());
            }
        }

        // Hiding the suggestions on the course.
        try {
            \tool_wizards\external\dismiss_course::execute($course->id);
            $this->assertTrue($offered, "$role dismissed");
        } catch (\required_capability_exception $e) {
            $this->assertFalse($offered, "$role could not dismiss");
        }
        $this->assertSame($offered, $DB->record_exists('tool_wizards_dismissed', ['userid' => $user->id]));
    }

    /**
     * The roles.
     *
     * @return array
     */
    public static function role_provider(): array {
        return [
            'student' => ['student', false],
            'non-editing teacher' => ['teacher', false],
            'editing teacher' => ['editingteacher', true],
            'manager' => ['manager', true],
        ];
    }
}
