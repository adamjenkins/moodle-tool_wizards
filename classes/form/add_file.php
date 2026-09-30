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
 * Mini-wizard: add a file for students to open or download (mod_resource).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_file extends add_module_base {
    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'file';
    }

    /**
     * The file types this mini-wizard accepts.
     *
     * @return array|string '*' or a list of extensions
     */
    protected static function accepted_types() {
        return '*';
    }

    /**
     * The intro string key for this mini-wizard.
     *
     * @return string
     */
    protected static function intro_string(): string {
        return 'add_file_intro';
    }

    /**
     * Questions: the file and, optionally, a name.
     */
    protected function define_questions(): void {
        global $CFG;
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string(static::intro_string(), 'tool_wizards')));
        $mform->addElement('filemanager', 'files', get_string('file_upload', 'tool_wizards'), null, [
            'maxfiles' => 1,
            'subdirs' => 0,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => static::accepted_types(),
        ]);
        $mform->addHelpButton('files', 'file_upload', 'tool_wizards');
        $this->add_name_field('file_name', false);
        $mform->addElement('static', 'namehint', '', get_string('file_name_hint', 'tool_wizards'));
    }

    /**
     * A new, empty draft area for the upload.
     */
    public function set_data_for_dynamic_submission(): void {
        $draftitemid = file_get_submitted_draft_itemid('files');
        file_prepare_draft_area($draftitemid, null, null, null, null);
        $this->set_data(['courseid' => $this->get_course()->id, 'files' => $draftitemid]);
    }

    /**
     * A file is required.
     *
     * @param array $data submitted data
     * @param array $files uploaded files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!self::first_draft_file((int) ($data['files'] ?? 0))) {
            $errors['files'] = get_string('error_nofile', 'tool_wizards');
        }
        return $errors;
    }

    /**
     * The name is optional: the file's name is used when it is left empty.
     *
     * @return bool
     */
    protected function name_is_required(): bool {
        return false;
    }

    /**
     * The module form fields.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    protected function answers_to_fields(\stdClass $data): array {
        $name = trim($data->name ?? '');
        if ($name === '') {
            $file = self::first_draft_file((int) $data->files);
            $name = $file ? pathinfo($file->get_filename(), PATHINFO_FILENAME) : get_string('file_defaultname', 'tool_wizards');
        }
        return ['name' => $name, 'files' => (int) $data->files];
    }

    /**
     * The first file in a draft area of the current user.
     *
     * @param int $draftitemid the draft area
     * @return \stored_file|null
     */
    protected static function first_draft_file(int $draftitemid): ?\stored_file {
        global $USER;
        if (!$draftitemid) {
            return null;
        }
        $fs = get_file_storage();
        $usercontext = \core\context\user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        return $files ? reset($files) : null;
    }
}
