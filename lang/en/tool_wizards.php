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
 * English language strings for tool_wizards.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['back'] = 'Back';
$string['category'] = 'Category';
$string['category_help'] = 'Categories group courses together, for example by department or year.';
$string['change'] = 'Change';
$string['change_label'] = 'Change {$a->name}';
$string['coursewizard'] = 'Course wizard';
$string['createcourse'] = 'Create course';
$string['createcoursewithwizard'] = 'Create a course with the wizard';
$string['emptythreshold'] = 'Suggest first content up to this many activities';
$string['emptythreshold_desc'] = 'Teachers see the "What would you like to add first?" suggestions on courses that have this many activities or fewer. The Announcements forum that every new course gets is not counted. Set to 0 to show them only on empty courses.';
$string['enabled'] = 'Enable the wizards';
$string['enabled_desc'] = 'Turn the course wizard and the first-content suggestions on or off for the whole site.';
$string['entrylinks'] = 'Show wizard links next to "Add a new course"';
$string['entrylinks_desc'] = 'Add a "Create a course with the wizard" link beside Moodle\'s own "Add a new course" buttons. The wizard is always available from the course category menu and from Site administration, even when this is off.';
$string['error_category'] = 'Please choose a category where you can create courses.';
$string['error_format'] = 'Please choose a layout.';
$string['error_fullname'] = 'Please give the course a name.';
$string['error_invalid'] = 'The course could not be created because some answers need changing.';
$string['error_numsections'] = 'Please enter a whole number from 0 to {$a->max}.';
$string['error_shortname'] = 'Please give the course a short name.';
$string['error_startdate'] = 'Please choose the day the course starts.';
$string['formatdesc_social'] = 'Your course centres on one discussion forum where everyone can post.';
$string['formatdesc_topics'] = 'Your course is split into sections that you name yourself, such as topics or units.';
$string['formatdesc_weeks'] = 'Your course is split into one section for each week, starting from the day you choose.';
$string['fullname'] = 'Course name';
$string['fullname_help'] = 'This is the name students and teachers see, for example "Introduction to Biology".';
$string['layout'] = 'Layout';
$string['next'] = 'Next';
$string['notenrolledwarning'] = 'Your course has been created, but you could not be added to it as a teacher. Please ask your site administrator to add you.';
$string['numsections'] = 'Number of sections';
$string['numsections_help'] = 'You can add or remove sections at any time later.';
$string['pluginname'] = 'Wizards';
$string['privacy:metadata:preference:hidesuggestions'] = 'Whether the user has chosen not to see wizard suggestions.';
$string['privacy:metadata:tool_wizards_dismissed'] = 'Courses where the user chose to hide the first-content suggestions.';
$string['privacy:metadata:tool_wizards_dismissed:courseid'] = 'The course where the suggestions are hidden.';
$string['privacy:metadata:tool_wizards_dismissed:timecreated'] = 'When the suggestions were hidden.';
$string['privacy:metadata:tool_wizards_dismissed:userid'] = 'The user who hid the suggestions.';
$string['privacy:metadata:tool_wizards_message'] = 'Messages waiting to be shown to the user, for example about newly unlocked activities.';
$string['privacy:metadata:tool_wizards_message:payload'] = 'The details of the message, such as which activities were unlocked.';
$string['privacy:metadata:tool_wizards_message:timecreated'] = 'When the message was queued.';
$string['privacy:metadata:tool_wizards_message:timeshown'] = 'When the message was shown to the user.';
$string['privacy:metadata:tool_wizards_message:type'] = 'The kind of message.';
$string['privacy:metadata:tool_wizards_message:userid'] = 'The user the message is for.';
$string['privacy:path:dismissed'] = 'Hidden suggestions';
$string['privacy:path:messages'] = 'Messages';
$string['privacy:preference:hidesuggestions:no'] = 'The user sees wizard suggestions.';
$string['privacy:preference:hidesuggestions:yes'] = 'The user has chosen not to see wizard suggestions.';
$string['progresstext'] = 'Step {$a->current} of {$a->total}';
$string['shortname'] = 'Short name';
$string['shortname_help'] = 'A short version of the name, shown in menus and at the top of pages. We have suggested one, and you can change it.';
$string['shortname_taken'] = 'Another course already uses this short name. How about "{$a->suggestion}"?';
$string['showallsettings'] = 'Show all settings';
$string['startdate'] = 'Start date';
$string['startdate_help'] = 'Each weekly section is dated from this day.';
$string['step_category_title'] = 'Where should the course go?';
$string['step_layout_help'] = 'The layout decides how your course page is organised. You can change it later.';
$string['step_layout_title'] = 'What layout do you want?';
$string['step_name_title'] = 'What do you want the course called?';
$string['step_review_help'] = 'Check your answers. You can change any of them before the course is created. Everything else uses your site\'s usual settings, which you can change later.';
$string['step_review_title'] = 'Ready to create your course';
$string['step_sections_title'] = 'How many sections do you want to start with?';
$string['step_startdate_title'] = 'When does the course start?';
$string['step_visibility_title'] = 'Should students see the course now?';
$string['stepannounce'] = 'Step {$a->current} of {$a->total}: {$a->title}';
$string['visibility'] = 'Visible to students';
$string['visible_later'] = 'Not yet, I will show it later';
$string['visible_later_help'] = 'The course stays hidden from students until you show it, in the course settings.';
$string['visible_later_help_task'] = 'The course stays hidden from students, and appears for them on its start date.';
$string['visible_later_summary'] = 'Hidden for now';
$string['visible_now'] = 'Yes, show it to students now';
$string['visible_now_summary'] = 'Shown to students now';
