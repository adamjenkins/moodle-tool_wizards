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

    /** @var array The display (resourcelib) each purpose uses: embed, force download, open. */
    const DISPLAY = ['read' => 1, 'download' => 4, 'print' => 5, 'view' => 1];

    /**
     * What students will do with the file, among the ways the site lets files open.
     *
     * @return string[]
     */
    protected function purposes(): array {
        return self::offered(['read', 'download', 'print']);
    }

    /**
     * The purposes whose display the site enables; none unless at least two are.
     *
     * @param string[] $purposes the candidates
     * @return string[]
     */
    protected static function offered(array $purposes): array {
        $enabled = array_map('intval', explode(',', (string) get_config('resource', 'displayoptions')));
        $offered = array_values(array_filter($purposes, fn($p) => in_array(self::DISPLAY[$p], $enabled, true)));
        return count($offered) > 1 ? $offered : [];
    }

    /**
     * The details students see next to the link, for each purpose.
     *
     * @return array
     */
    public static function presets(): array {
        return [
            'read' => ['showsize' => 0, 'showtype' => 1, 'showdate' => 0, 'completionchoice' => 'view'],
            'download' => ['showsize' => 1, 'showtype' => 1, 'showdate' => 0, 'completionchoice' => 'view'],
            'print' => ['showsize' => 1, 'showtype' => 1, 'showdate' => 1, 'completionchoice' => 'view'],
            'view' => ['showsize' => 0, 'showtype' => 0, 'showdate' => 0, 'completionchoice' => 'view'],
        ];
    }

    /**
     * The file's own screen: the details shown next to its link.
     *
     * @return string[]
     */
    protected function type_steps(): array {
        return ['details'];
    }

    /**
     * Details: size, type and date next to the link.
     */
    protected function define_step_details(): void {
        $mform = $this->_form;
        foreach (['showsize', 'showtype', 'showdate'] as $name) {
            $mform->addElement('advcheckbox', $name, '', get_string('file_' . $name, 'tool_wizards'));
            $mform->setDefault($name, (int) get_config('resource', $name));
        }
    }

    /**
     * Completion when the student opens the file.
     *
     * @return array
     */
    protected function completion_choices(): array {
        return ['view' => [
            'label' => get_string('completion_view', 'tool_wizards'),
            'desc' => get_string('file_completion_view_desc', 'tool_wizards'),
            'fields' => ['completionview' => 1],
        ]];
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
     * @return array errors
     */
    protected function own_validation(array $data): array {
        if (!self::first_draft_file((int) ($data['files'] ?? 0))) {
            return ['files' => get_string('error_nofile', 'tool_wizards')];
        }
        return [];
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
        $fields = ['name' => $name, 'files' => (int) $data->files];
        // The display only where the site offers a choice: with one option the form holds it fixed.
        $purpose = $data->purpose ?? '';
        if ($purpose !== '' && in_array($purpose, $this->purposes(), true)) {
            $fields['display'] = self::DISPLAY[$purpose];
        }
        foreach (['showsize', 'showtype', 'showdate'] as $name) {
            if (isset($data->$name)) {
                $fields[$name] = (int) $data->$name;
            }
        }
        return $fields;
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
