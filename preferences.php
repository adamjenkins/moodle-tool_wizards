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
 * The current user's wizard preferences: switch suggestions back on.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use tool_wizards\local\prompt;

require_login(null, false);
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}

$url = new moodle_url('/admin/tool/wizards/preferences.php');
$PAGE->set_url($url);
$PAGE->set_context(\core\context\user::instance($USER->id));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('preferences_title', 'tool_wizards'));
$PAGE->set_heading(fullname($USER));
$PAGE->navbar->add(get_string('preferences'), new moodle_url('/user/preferences.php'));
$PAGE->navbar->add(get_string('preferences_title', 'tool_wizards'), $url);

$returnurl = new moodle_url('/user/preferences.php');
$dismissed = $DB->count_records('tool_wizards_dismissed', ['userid' => $USER->id]);
$form = new \tool_wizards\form\preferences_form($url, ['dismissed' => $dismissed]);
$form->set_data(['showsuggestions' => prompt::suggestions_hidden() ? 0 : 1, 'resetcourses' => 0]);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    set_user_preference(prompt::PREF_HIDE, empty($data->showsuggestions) ? 1 : 0);
    if (!empty($data->resetcourses)) {
        $DB->delete_records('tool_wizards_dismissed', ['userid' => $USER->id]);
    }
    redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('preferences_title', 'tool_wizards'));
$form->display();
echo $OUTPUT->footer();
