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

/**
 * Behat steps for tool_wizards.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;
use Moodle\BehatExtension\Exception\SkippedException;

/**
 * Behat steps for tool_wizards.
 */
class behat_tool_wizards extends behat_base {
    /**
     * Convert page names to URLs for 'I am on the "[page]" page' with the tool_wizards prefix.
     *
     * | page             | description                          |
     * | Course wizard    | The course wizard                    |
     * | Preferences      | The user's own wizard preferences    |
     *
     * @param string $page the page name
     * @return moodle_url
     * @throws Exception for an unknown page
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'course wizard':
                return new moodle_url('/admin/tool/wizards/course.php');
            case 'preferences':
                return new moodle_url('/admin/tool/wizards/preferences.php');
        }
        throw new Exception("Unrecognised tool_wizards page '{$page}'");
    }

    /**
     * Report a Teacher scaffold unlock for a user, as tool_teacherscaffold does when a teacher
     * reaches a new stage. Skips the scenario when that plugin is not installed.
     *
     * @Given /^Teacher scaffold reports that "(?P<username>[^"]*)" unlocked "(?P<modules>[^"]*)"$/
     * @param string $username the teacher
     * @param string $modules comma-separated module names, for example "quiz,choice"
     * @throws SkippedException when tool_teacherscaffold is not installed
     */
    public function teacher_scaffold_reports_unlock(string $username, string $modules): void {
        global $DB;
        if (!class_exists('\\tool_teacherscaffold\\event\\tier_unlocked')) {
            throw new SkippedException('tool_teacherscaffold is not installed.');
        }
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        \tool_teacherscaffold\event\tier_unlocked::create([
            'context' => \context_system::instance(),
            'relateduserid' => $user->id,
            'other' => ['tier' => 2, 'unlockedmodules' => array_map('trim', explode(',', $modules))],
        ])->trigger();
    }

    /**
     * Check a setting the wizard saved on a quiz, straight from the database: the quiz settings
     * page, with its many editors, can be too slow to load reliably here.
     *
     * @Then /^the quiz "(?P<name>[^"]*)" has "(?P<field>[a-z_]+)" set to "(?P<value>[^"]*)"$/
     * @param string $name the quiz name
     * @param string $field a column of the quiz table
     * @param string $value the expected value
     * @throws ExpectationException when it differs
     */
    public function the_quiz_has_set_to(string $name, string $field, string $value): void {
        global $DB;
        $actual = (string) $DB->get_field('quiz', $field, ['name' => $name], MUST_EXIST);
        if ($actual !== $value) {
            throw new ExpectationException("Quiz \"$name\" has $field \"$actual\", expected \"$value\".", $this->getSession());
        }
    }
}
