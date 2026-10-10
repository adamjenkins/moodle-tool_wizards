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

namespace tool_wizards\local\wizard\content;

use tool_wizards\local\wizard\content;

/**
 * Adds an example submission to a workshop that uses examples.
 *
 * Saved as mod/workshop/exsubmission.php saves a new example (insert, then the editor's and the
 * file manager's draft files, then update), and checked with workshop::validate_submission_data()
 * as that page's form (mod/workshop/submission_form.php) does. The teacher's reference assessment
 * of the example is made afterwards on the workshop page, as usual.
 *
 * Fields:
 * - title: the example's title (required, up to 255 characters);
 * - content: the example's text (editor value or plain text; when the workshop accepts text);
 * - attachment: a draft item id of files (when the workshop accepts files).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshop_example extends content {
    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'workshop';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'title' => 'the example\'s title',
            'content' => 'the example\'s text (editor or text)',
            'attachment' => 'files: a draft item id',
        ];
    }

    /**
     * Page types: the workshop page (where examples are listed and assessed).
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-workshop-view'];
    }

    /**
     * Managing examples needs the workshop's own capability (as exsubmission.php).
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/workshop:manageexamples'];
    }

    /**
     * The workshop API object.
     *
     * @param \cm_info $cm the activity
     * @return \workshop
     */
    protected static function workshop(\cm_info $cm): \workshop {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/workshop/locallib.php');
        $record = $DB->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST);
        return new \workshop($record, $cm, $cm->get_course());
    }

    /**
     * Only for workshops that use examples (the "Use examples" setting).
     *
     * @param \cm_info $cm the activity
     * @return bool
     */
    public static function is_available(\cm_info $cm): bool {
        return !empty(self::workshop($cm)->useexamples);
    }

    /**
     * Whether the workshop takes text and files, for {"offered": {"field": "text", "value": 1}}.
     *
     * @param \cm_info $cm the activity
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        $workshop = self::workshop($cm);
        return [
            'text' => [$workshop->submissiontypetext == WORKSHOP_SUBMISSION_TYPE_DISABLED ? '0' : '1'],
            'file' => [$workshop->submissiontypefile == WORKSHOP_SUBMISSION_TYPE_DISABLED ? '0' : '1'],
        ];
    }

    /**
     * An editor value from a field that may be an editor array or plain text.
     *
     * @param mixed $value the field
     * @return array text, format and (when the editor had one) itemid
     */
    protected static function editor($value): array {
        if (is_array($value)) {
            return ['text' => (string) ($value['text'] ?? ''), 'format' => (int) ($value['format'] ?? FORMAT_HTML)]
                + (empty($value['itemid']) ? [] : ['itemid' => (int) $value['itemid']]);
        }
        $text = trim((string) $value);
        return ['text' => $text === '' ? '' : '<p>' . nl2br(s($text), false) . '</p>', 'format' => FORMAT_HTML];
    }

    /**
     * The submission form's data for these fields.
     *
     * @param \workshop $workshop the workshop
     * @param array $fields the fields
     * @return array
     */
    protected static function formdata(\workshop $workshop, array $fields): array {
        $data = [
            'id' => 0,
            'example' => 1,
            'cmid' => $workshop->cm->id,
            'workshopid' => $workshop->id,
            'title' => trim((string) ($fields['title'] ?? '')),
            'content_editor' => self::editor($fields['content'] ?? ''),
        ];
        if (!empty($fields['attachment'])) {
            $data['attachment_filemanager'] = (int) $fields['attachment'];
        }
        return $data;
    }

    /**
     * Check the fields as the example form does.
     *
     * @param \cm_info $cm the activity
     * @param array $fields the fields
     * @return array field => message
     */
    public static function check(\cm_info $cm, array $fields): array {
        $workshop = self::workshop($cm);
        $data = self::formdata($workshop, $fields);
        $errors = [];
        if ($data['title'] === '') {
            $errors['title'] = get_string('required');
        } else if (\core_text::strlen($data['title']) > 255) {
            $errors['title'] = get_string('maximumchars', '', 255);
        }
        $names = ['title' => 'title', 'content_editor' => 'content', 'attachment_filemanager' => 'attachment'];
        foreach ($workshop->validate_submission_data($data) as $element => $message) {
            $field = $names[$element] ?? 'title';
            $errors[$field] = isset($errors[$field]) ? $errors[$field] . ' ' . $message : $message;
        }
        return $errors;
    }

    /**
     * Save the example as exsubmission.php does.
     *
     * @param \cm_info $cm the activity
     * @param array $fields the fields
     * @return string e.g. "Example: A good essay"
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $DB, $USER;
        $workshop = self::workshop($cm);
        $data = self::formdata($workshop, $fields);

        $timenow = time();
        $formdata = (object) [
            'workshopid' => $workshop->id,
            'example' => 1,
            'authorid' => $USER->id,
            'timecreated' => $timenow,
            'timemodified' => $timenow,
            'feedbackauthorformat' => editors_get_preferred_format(),
            'title' => $data['title'],
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'contenttrust' => 0,
            'content_editor' => $data['content_editor'],
        ];
        if (isset($data['attachment_filemanager'])) {
            $formdata->attachment_filemanager = $data['attachment_filemanager'];
        }
        $formdata->id = $DB->insert_record('workshop_submissions', $formdata);

        // Save the embedded and attached files, as exsubmission.php.
        $formdata = file_postupdate_standard_editor(
            $formdata,
            'content',
            $workshop->submission_content_options(),
            $workshop->context,
            'mod_workshop',
            'submission_content',
            $formdata->id
        );
        if (isset($formdata->attachment_filemanager)) {
            $formdata = file_postupdate_standard_filemanager(
                $formdata,
                'attachment',
                $workshop->submission_attachment_options(),
                $workshop->context,
                'mod_workshop',
                'submission_attachment',
                $formdata->id
            );
        }
        if (empty($formdata->attachment)) {
            $formdata->attachment = 0;
        }
        $DB->update_record('workshop_submissions', $formdata);

        $title = format_string($data['title'], true, ['context' => $cm->context, 'escape' => false]);
        return get_string('workshopexample_added', 'tool_wizards', $title);
    }

    /**
     * Where the teacher sees the result: the workshop page, where the example is assessed.
     *
     * @param \cm_info $cm the activity
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/workshop/view.php', ['id' => $cm->id]);
    }
}
