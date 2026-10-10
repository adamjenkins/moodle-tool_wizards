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
        if (!self::wizards_enabled() || !isloggedin() || isguestuser() || during_initial_install()) {
            return;
        }
        if (self::is_page($page, ['/course/view.php'])) {
            self::section_links($hook);
            return;
        }
        if (self::content_link($hook)) {
            return;
        }
        if (!get_config('tool_wizards', 'entrylinks') || !self::is_page($page, self::ENTRY_LINK_PAGES)) {
            return;
        }
        if (!local\wizard\repository::course_wizards() || !course_creator::get_categories()) {
            return;
        }
        $page->requires->js_call_amd('tool_wizards/entrylink', 'init', [
            (new \moodle_url('/admin/tool/wizards/course.php'))->out(false),
            get_string('createcoursewithwizard', 'tool_wizards'),
        ]);
    }

    /**
     * "Add with a wizard" beside each section's "Add an activity or resource", in edit mode.
     *
     * The list of wizards is rendered here into a hidden template element, so the script that
     * places the links only needs to know where it is.
     *
     * @param before_footer_html_generation $hook the hook
     */
    protected static function section_links(before_footer_html_generation $hook): void {
        $page = $hook->renderer->get_page();
        $course = $page->course;
        self::try_wizard($page);
        if (empty($course->id) || $course->id == SITEID || !$page->user_is_editing()) {
            return;
        }
        $wizards = [];
        foreach (local\wizard\repository::for_course($course) as $record) {
            $wizards[] = self::list_item($page, $record, local\wizard\repository::definition($record));
        }
        if (!$wizards) {
            return;
        }
        $list = $hook->renderer->render_from_template('tool_wizards/wizard_list', ['wizards' => $wizards]);
        $hook->add_html(\html_writer::tag('template', $list, [
            'id' => 'tool_wizards-wizardlist',
            'data-courseid' => (int) $course->id,
            'data-label' => get_string('addwithwizard', 'tool_wizards'),
            'data-choose' => get_string('choosewizard', 'tool_wizards'),
        ]));
        $page->requires->js_call_amd('tool_wizards/section_link', 'init', ['#tool_wizards-wizardlist']);
    }

    /**
     * "Add with a wizard" on an activity's own pages where its in-activity wizards add content
     * (the quiz's questions page, the lesson's edit page, ...).
     *
     * @param before_footer_html_generation $hook the hook
     * @return bool whether this was such a page
     */
    protected static function content_link(before_footer_html_generation $hook): bool {
        $page = $hook->renderer->get_page();
        $cm = $page->cm;
        // Read through the page's magic getter: empty() on it would always be true (moodle_page has no __isset()).
        $pagetype = (string) $page->pagetype;
        if (!$cm instanceof \cm_info || $pagetype === '') {
            return false;
        }
        $wizards = [];
        foreach (local\wizard\repository::for_cm($cm) as $record) {
            $doc = local\wizard\repository::definition($record);
            $handler = local\content_creator::handler($doc);
            if (!in_array($pagetype, $handler::pagetypes(), true)) {
                continue;
            }
            $wizards[] = self::list_item($page, $record, $doc);
        }
        if (!$wizards) {
            return false;
        }
        $list = $hook->renderer->render_from_template('tool_wizards/wizard_list', ['wizards' => $wizards]);
        $hook->add_html(\html_writer::tag('template', $list, [
            'id' => 'tool_wizards-contentlist',
            'data-cmid' => (int) $cm->id,
            'data-label' => get_string('addwithwizard', 'tool_wizards'),
            'data-choose' => get_string('choosewizard', 'tool_wizards'),
        ]));
        $page->requires->js_call_amd('tool_wizards/content_link', 'init', ['#tool_wizards-contentlist']);
        return true;
    }

    /**
     * One wizard in a list of wizards to choose from.
     *
     * @param \moodle_page $page the page
     * @param \stdClass $record the wizard
     * @param array $doc its definition
     * @return array
     */
    public static function list_item(\moodle_page $page, \stdClass $record, array $doc): array {
        $modname = local\wizard\repository::modname_of($record);
        return [
            'key' => $record->wizardkey,
            'title' => local\wizard\text::get($doc['title'] ?? $record->wizardkey),
            'heading' => local\wizard\text::get($doc['heading'] ?? $doc['title'] ?? ''),
            'description' => local\wizard\text::get($doc['description'] ?? ''),
            'icon' => $page->get_renderer('core')->image_url('monologo', 'mod_' . $modname)->out(false),
            'purpose' => self::purpose($modname),
        ];
    }

    /**
     * An activity's purpose, which colours its icon as in Moodle's activity chooser.
     *
     * @param string $modname the module
     * @return string
     */
    public static function purpose(string $modname): string {
        return (string) plugin_supports('mod', $modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER);
    }

    /**
     * "Try it" from the wizard list: open the wizard in preview on this course page.
     *
     * @param \moodle_page $page the course page
     */
    protected static function try_wizard(\moodle_page $page): void {
        $key = optional_param('tool_wizards_try', '', PARAM_ALPHANUMEXT);
        if ($key === '' || !has_capability('tool/wizards:managewizards', \core\context\system::instance())) {
            return;
        }
        $record = local\wizard\repository::get_by_key($key);
        if ($record && local\wizard\repository::is_content($record)) {
            self::try_content_wizard($page, $record);
            return;
        }
        if (
            !$record || $record->target === 'course'
                || !\tool_wizards\local\module_creator::is_available($page->course, $record->target)
        ) {
            return;
        }
        $doc = local\wizard\repository::definition($record);
        $page->requires->js_call_amd('tool_wizards/open_wizard', 'openWizard', [[
            'courseid' => (int) $page->course->id,
            'wizard' => $record->wizardkey,
            'title' => get_string('preview_title', 'tool_wizards') . ': '
                . local\wizard\text::get($doc['heading'] ?? $doc['title'] ?? ''),
            'preview' => true,
        ]]);
    }

    /**
     * "Try it" for an in-activity wizard: open it in preview on the course's first activity it can be used on.
     *
     * @param \moodle_page $page the course page
     * @param \stdClass $record the wizard
     */
    protected static function try_content_wizard(\moodle_page $page, \stdClass $record): void {
        $doc = local\wizard\repository::definition($record);
        foreach (get_fast_modinfo($page->course)->get_instances_of(local\wizard\repository::modname_of($record)) as $cm) {
            if ($cm->deletioninprogress || !local\content_creator::may_use($doc, $cm)) {
                continue;
            }
            $page->requires->js_call_amd('tool_wizards/open_wizard', 'openContentWizard', [[
                'cmid' => (int) $cm->id,
                'wizard' => $record->wizardkey,
                'title' => get_string('preview_title', 'tool_wizards') . ': '
                    . local\wizard\text::get($doc['heading'] ?? $doc['title'] ?? ''),
                'preview' => true,
            ]]);
            return;
        }
        \core\notification::warning(get_string('tryit_noactivity', 'tool_wizards'));
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
     * Add "Bring back the course wizards" to the footer's help popover, under core's
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
