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
 * Library callbacks for tool_wizards.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Offer the course wizard in a course category's "More" menu.
 *
 * @param navigation_node $categorynode the category settings node
 * @param context_coursecat $catcontext the category context
 */
function tool_wizards_extend_navigation_category_settings(navigation_node $categorynode, context_coursecat $catcontext): void {
    if (!get_config('tool_wizards', 'enabled') || !has_capability('moodle/course:create', $catcontext)) {
        return;
    }
    $categorynode->add(
        get_string('createcoursewithwizard', 'tool_wizards'),
        new moodle_url('/admin/tool/wizards/course.php', ['category' => $catcontext->instanceid]),
        navigation_node::TYPE_SETTING,
        null,
        'tool_wizards_course',
        new pix_icon('t/add', '')
    );
}

/**
 * Offer the course wizard in the site home's "More" menu, to people who can create
 * courses in the category core's own front-page button uses.
 *
 * @param navigation_node $frontpage the site home settings node
 * @param stdClass $course the site course
 * @param context_course $coursecontext the site course context
 */
function tool_wizards_extend_navigation_frontpage(
    navigation_node $frontpage,
    stdClass $course,
    context_course $coursecontext
): void {
    global $CFG;
    if (!get_config('tool_wizards', 'enabled') || !isloggedin() || isguestuser()) {
        return;
    }
    $categoryid = \tool_wizards\local\course_creator::get_start_category((int) ($CFG->defaultrequestcategory ?? 0));
    if (!$categoryid) {
        return;
    }
    $frontpage->add(
        get_string('createcoursewithwizard', 'tool_wizards'),
        new moodle_url('/admin/tool/wizards/course.php', ['category' => $categoryid]),
        navigation_node::TYPE_SETTING,
        null,
        'tool_wizards_course',
        new pix_icon('t/add', '')
    );
}

/**
 * Declare the user preferences this plugin lets the current user set, so that the
 * core_user/repository JS API (and the core_user_set_user_preferences web service)
 * accept them.
 *
 * @return array preference definitions, keyed by name
 */
function tool_wizards_user_preferences(): array {
    return [
        'tool_wizards_hidesuggestions' => [
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'choices' => [0, 1],
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
