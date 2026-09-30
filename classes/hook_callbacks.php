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

use core\hook\output\after_http_headers;
use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_footer_html_generation;
use tool_wizards\local\course_creator;

/**
 * Output hook callbacks: the wizard links next to "Add a new course", the
 * first-content suggestions on course pages, and the footer link that brings them back.
 *
 * Both run on every page, so each bails out early and cheaply.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /** @var string[] Pages that show core "Add a new course" or "Create course" buttons. */
    const ENTRY_LINK_PAGES = ['/course/index.php', '/course/management.php', '/my/courses.php', '/my/index.php', '/index.php'];

    /**
     * Add the "Create a course with the wizard" links next to core's buttons.
     *
     * Core offers no way to add a button beside its own, so a small script places a
     * link next to each one it finds. If core's markup changes the links simply do
     * not appear; nothing else depends on them.
     *
     * @param before_footer_html_generation $hook the hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        $page = $hook->renderer->get_page();
        if (!self::wizards_enabled() || !get_config('tool_wizards', 'entrylinks') || !isloggedin() || isguestuser()) {
            return;
        }
        if (during_initial_install() || !self::is_page($page, self::ENTRY_LINK_PAGES)) {
            return;
        }
        if (!course_creator::get_categories()) {
            return;
        }
        $page->requires->js_call_amd('tool_wizards/entrylink', 'init', [
            (new \moodle_url('/admin/tool/wizards/course.php'))->out(false),
            get_string('createcoursewithwizard', 'tool_wizards'),
        ]);
    }

    /**
     * Show the first-content suggestions, and any queued message, at the top of a course page.
     *
     * @param after_http_headers $hook the hook
     */
    public static function after_http_headers(after_http_headers $hook): void {
        $page = $hook->renderer->get_page();
        if (!self::wizards_enabled() || !self::is_page($page, ['/course/view.php'])) {
            return;
        }
        $html = local\prompt::render_for_page($page, $hook->renderer);
        if ($html !== '') {
            $hook->add_html($html);
        }
    }

    /**
     * Add "Bring the wizards back" to the footer's help popover, beside core's
     * "Reset user tour on this page", on a course page where the user hid the suggestions.
     *
     * @param before_standard_footer_html_generation $hook the hook
     */
    public static function before_standard_footer(before_standard_footer_html_generation $hook): void {
        $page = $hook->renderer->get_page();
        if (!self::wizards_enabled() || during_initial_install() || !self::is_page($page, ['/course/view.php'])) {
            return;
        }
        if (!local\prompt::may_bring_back($page->course)) {
            return;
        }
        $url = new \moodle_url('/admin/tool/wizards/showagain.php', ['courseid' => $page->course->id, 'sesskey' => sesskey()]);
        $hook->add_html(\html_writer::div(
            \html_writer::link($url, get_string('bringback', 'tool_wizards')),
            'tool_wizards-bringback'
        ));
    }

    /**
     * Whether the site has the wizards switched on.
     *
     * @return bool
     */
    public static function wizards_enabled(): bool {
        return (bool) get_config('tool_wizards', 'enabled');
    }

    /**
     * Whether the page is one of these scripts.
     *
     * @param \moodle_page $page the page
     * @param string[] $paths script paths relative to wwwroot
     * @return bool
     */
    protected static function is_page(\moodle_page $page, array $paths): bool {
        if (!$page->has_set_url()) {
            return false;
        }
        foreach ($paths as $path) {
            if ($page->url->compare(new \moodle_url($path), URL_MATCH_BASE)) {
                return true;
            }
        }
        return false;
    }
}
