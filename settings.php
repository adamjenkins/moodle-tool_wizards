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
 * Admin settings and the admin-tree entry point for tool_wizards.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The course wizard sits next to core's "Add a new course" page in Site administration > Courses.
// It is registered outside the $hassiteconfig block so that system-level course creators, who
// hold moodle/course:create but not moodle/site:config, can reach it too.
if (get_config('tool_wizards', 'enabled') !== '0') {
    $ADMIN->add('courses', new admin_externalpage(
        'tool_wizards_course',
        new lang_string('createcoursewithwizard', 'tool_wizards'),
        new moodle_url('/admin/tool/wizards/course.php'),
        'moodle/course:create'
    ));
}

// Wizards: settings and the list of wizards, under Site administration > Courses.
$ADMIN->add('courses', new admin_category('tool_wizards', new lang_string('pluginname', 'tool_wizards')));
$ADMIN->add('tool_wizards', new admin_externalpage(
    'tool_wizards_manage',
    new lang_string('managewizards', 'tool_wizards'),
    new moodle_url('/admin/tool/wizards/manage.php'),
    'tool/wizards:managewizards'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_wizards_settings', new lang_string('settings', 'tool_wizards'));

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'tool_wizards/enabled',
            new lang_string('enabled', 'tool_wizards'),
            new lang_string('enabled_desc', 'tool_wizards'),
            1
        ));

        $settings->add(new admin_setting_configtext(
            'tool_wizards/emptythreshold',
            new lang_string('emptythreshold', 'tool_wizards'),
            new lang_string('emptythreshold_desc', 'tool_wizards'),
            1,
            PARAM_INT,
            3
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_wizards/entrylinks',
            new lang_string('entrylinks', 'tool_wizards'),
            new lang_string('entrylinks_desc', 'tool_wizards'),
            1
        ));
    }

    $ADMIN->add('tool_wizards', $settings);
}
