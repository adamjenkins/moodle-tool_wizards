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

/**
 * Mini-wizard: add a forum (mod_forum).
 *
 * The purpose picks the kind of forum (general, Q and A, single discussion and so on),
 * then small screens cover emails, deadlines, grading and keeping discussions manageable.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_forum extends add_module_base {
    /** @var array The forum type for each purpose. */
    const TYPES = [
        'general' => 'general',
        'questions' => 'general',
        'qanda' => 'qanda',
        'single' => 'single',
        'eachuser' => 'eachuser',
        'blog' => 'blog',
    ];

    /** @var array Lock discussions after this long without a reply, by choice. */
    const LOCK_AFTER = ['off' => 0, 'week' => WEEKSECS, 'fortnight' => 2 * WEEKSECS, 'month' => 4 * WEEKSECS];

    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'forum';
    }

    /**
     * The purposes. A single discussion cannot be used where the course forces separate groups.
     *
     * @return string[]
     */
    protected function purposes(): array {
        $course = $this->get_course();
        $purposes = array_keys(self::TYPES);
        if ($course->groupmodeforce && (int) $course->groupmode === SEPARATEGROUPS) {
            $purposes = array_values(array_diff($purposes, ['single']));
        }
        return $purposes;
    }

    /**
     * The choices that suit each purpose.
     *
     * @return array
     */
    public static function presets(): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $base = ['gradingchoice' => 'none', 'lockafter' => 'off', 'postlimit' => 0];
        return [
            'general' => $base + ['forcesubscribe' => FORUM_INITIALSUBSCRIBE, 'completionchoice' => 'posts'],
            'questions' => $base + ['forcesubscribe' => FORUM_CHOOSESUBSCRIBE, 'completionchoice' => self::COMPLETION_DEFAULT],
            'qanda' => $base + ['forcesubscribe' => FORUM_CHOOSESUBSCRIBE, 'completionchoice' => 'replies'],
            'single' => $base + ['forcesubscribe' => FORUM_INITIALSUBSCRIBE, 'completionchoice' => 'replies'],
            'eachuser' => $base + ['forcesubscribe' => FORUM_CHOOSESUBSCRIBE, 'completionchoice' => 'discussions'],
            'blog' => $base + ['forcesubscribe' => FORUM_CHOOSESUBSCRIBE, 'completionchoice' => 'discussions'],
        ];
    }

    /**
     * The forum's own screens.
     *
     * @return string[]
     */
    protected function type_steps(): array {
        return ['emails', 'deadline', 'grading', 'tidy'];
    }

    /**
     * Questions: a name, what the forum is for, and its description.
     */
    protected function define_questions(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('add_forum_intro', 'tool_wizards')));
        $this->add_name_field('forum_name');
        $this->add_description_field('forum_description');
    }

    /**
     * Emails: who is subscribed.
     */
    protected function define_step_emails(): void {
        $mform = $this->_form;
        $this->add_choices('forcesubscribe', get_string('forum_step_emails', 'tool_wizards'), [
            FORUM_CHOOSESUBSCRIBE => self::choice('forum_subscribe_optional'),
            FORUM_INITIALSUBSCRIBE => self::choice('forum_subscribe_auto'),
            FORUM_FORCESUBSCRIBE => self::choice('forum_subscribe_forced'),
            FORUM_DISALLOWSUBSCRIBE => self::choice('forum_subscribe_none'),
        ]);
        $mform->setType('forcesubscribe', PARAM_INT);
        $mform->setDefault('forcesubscribe', (int) get_config('forum', 'forum_subscription'));
    }

    /**
     * Deadline: a due date and a cut-off date, both optional.
     */
    protected function define_step_deadline(): void {
        $mform = $this->_form;
        $mform->addElement('date_time_selector', 'duedate', get_string('forum_duedate', 'tool_wizards'), ['optional' => true]);
        $mform->addElement('static', 'duedatehint', '', get_string('forum_duedate_hint', 'tool_wizards'));
        $label = get_string('forum_cutoffdate', 'tool_wizards');
        $mform->addElement('date_time_selector', 'cutoffdate', $label, ['optional' => true]);
        $mform->addElement('static', 'cutoffdatehint', '', get_string('forum_cutoffdate_hint', 'tool_wizards'));
    }

    /**
     * Grading: none, one grade for the whole forum, or rated posts.
     */
    protected function define_step_grading(): void {
        $mform = $this->_form;
        $this->add_choices('gradingchoice', get_string('forum_step_grading', 'tool_wizards'), [
            'none' => self::choice('forum_grading_none'),
            'whole' => self::choice('forum_grading_whole'),
            'ratings' => self::choice('forum_grading_ratings'),
        ]);
        $mform->setType('gradingchoice', PARAM_ALPHA);
        $mform->setDefault('gradingchoice', 'none');
        $this->add_max_grade_field('gradingchoice', 'none');
    }

    /**
     * Tidy: lock quiet discussions, and limit how often students post.
     */
    protected function define_step_tidy(): void {
        $mform = $this->_form;
        $options = [];
        foreach (array_keys(self::LOCK_AFTER) as $key) {
            $options[$key] = get_string('forum_lock_' . $key, 'tool_wizards');
        }
        $mform->addElement('select', 'lockafter', get_string('forum_lockafter', 'tool_wizards'), $options);
        $mform->addElement('static', 'lockafterhint', '', get_string('forum_lockafter_hint', 'tool_wizards'));
        $mform->addElement(
            'advcheckbox',
            'postlimit',
            get_string('forum_postlimit', 'tool_wizards'),
            get_string('forum_postlimit_label', 'tool_wizards')
        );
    }

    /**
     * Completion by posting, by starting discussions, or by replying. Each choice switches the
     * other rules off: a new forum has "post once" ticked already.
     *
     * @return array
     */
    protected function completion_choices(): array {
        $off = ['completionpostsenabled' => 0, 'completiondiscussionsenabled' => 0, 'completionrepliesenabled' => 0];
        return [
            'posts' => [
                'label' => get_string('forum_completion_posts', 'tool_wizards'),
                'desc' => get_string('forum_completion_posts_desc', 'tool_wizards'),
                'fields' => array_replace($off, ['completionpostsenabled' => 1, 'completionposts' => 1]),
            ],
            'discussions' => [
                'label' => get_string('forum_completion_discussions', 'tool_wizards'),
                'desc' => get_string('forum_completion_discussions_desc', 'tool_wizards'),
                'fields' => array_replace($off, ['completiondiscussionsenabled' => 1, 'completiondiscussions' => 1]),
            ],
            'replies' => [
                'label' => get_string('forum_completion_replies', 'tool_wizards'),
                'desc' => get_string('forum_completion_replies_desc', 'tool_wizards'),
                'fields' => array_replace($off, ['completionrepliesenabled' => 1, 'completionreplies' => 1]),
            ],
        ];
    }

    /**
     * Where core's forum form errors belong.
     *
     * @return array
     */
    protected function error_owners(): array {
        return [
            'type' => 'purposegroup',
            'duedate' => 'duedate',
            'cutoffdate' => 'cutoffdate',
            'grade_forum' => 'maxgrade',
            'scale' => 'maxgrade',
            'completionposts' => 'completionchoicegroup',
            'completiondiscussions' => 'completionchoicegroup',
            'completionreplies' => 'completionchoicegroup',
        ];
    }

    /**
     * A single discussion starts from the description, so it needs one; a grade needs a maximum.
     *
     * @param array $data submitted data
     * @return array errors
     */
    protected function own_validation(array $data): array {
        $errors = [];
        if (($data['purpose'] ?? '') === 'single' && trim($data['description'] ?? '') === '') {
            $errors['description'] = get_string('error_singledescription', 'tool_wizards');
        }
        return array_merge($errors, $this->max_grade_errors($data, 'gradingchoice', 'none'));
    }

    /**
     * The module form fields.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    protected function answers_to_fields(\stdClass $data): array {
        global $CFG;
        require_once($CFG->dirroot . '/rating/lib.php');
        $fields = [
            'name' => $data->name,
            'introeditor' => self::description_editor($data->description ?? ''),
            'type' => self::TYPES[$data->purpose ?? 'general'] ?? 'general',
        ];
        if (isset($data->forcesubscribe)) {
            $fields['forcesubscribe'] = (int) $data->forcesubscribe;
        }
        if (in_array('deadline', $this->steps, true)) {
            $fields['duedate'] = self::date_fields((int) ($data->duedate ?? 0));
            $fields['cutoffdate'] = self::date_fields((int) ($data->cutoffdate ?? 0));
        }
        $max = (string) (int) ($data->maxgrade ?? 10);
        switch ($data->gradingchoice ?? 'none') {
            case 'whole':
                $fields['grade_forum'] = ['modgrade_type' => 'point', 'modgrade_point' => $max];
                $fields['assessed'] = 0;
                break;
            case 'ratings':
                $fields['assessed'] = RATING_AGGREGATE_AVERAGE;
                $fields['scale'] = ['modgrade_type' => 'point', 'modgrade_point' => $max];
                $fields['grade_forum'] = ['modgrade_type' => 'none'];
                break;
            default:
                $fields['assessed'] = 0;
                $fields['grade_forum'] = ['modgrade_type' => 'none'];
        }
        if (isset($data->lockafter) && isset(self::LOCK_AFTER[$data->lockafter])) {
            $fields['lockdiscussionafter'] = self::duration_fields(self::LOCK_AFTER[$data->lockafter]);
        }
        if (!empty($data->postlimit)) {
            $fields += ['blockperiod' => DAYSECS, 'blockafter' => 5, 'warnafter' => 4];
        } else if (isset($data->postlimit)) {
            $fields['blockperiod'] = 0;
        }
        return $fields;
    }
}
