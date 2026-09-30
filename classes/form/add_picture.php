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
 * Mini-wizard: show a picture on the course page, in a text and media area (mod_label).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_picture extends add_module_base {
    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'picture';
    }

    /**
     * Questions: the picture, what it shows (for people who cannot see it), and an optional caption.
     */
    protected function define_questions(): void {
        global $CFG;
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('add_picture_intro', 'tool_wizards')));
        $mform->addElement('filemanager', 'picture', get_string('picture_file', 'tool_wizards'), null, [
            'maxfiles' => 1,
            'subdirs' => 0,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => ['web_image'],
        ]);
        $mform->addHelpButton('picture', 'file_upload', 'tool_wizards');
        $mform->addElement('text', 'alt', get_string('picture_alt', 'tool_wizards'), ['size' => 40]);
        $mform->setType('alt', PARAM_TEXT);
        $mform->addHelpButton('alt', 'picture_alt', 'tool_wizards');
        $mform->addRule('alt', get_string('required'), 'required', null, 'client');
        $mform->addElement('text', 'caption', get_string('picture_caption', 'tool_wizards'), ['size' => 40]);
        $mform->setType('caption', PARAM_TEXT);
    }

    /**
     * A new, empty draft area for the picture.
     */
    public function set_data_for_dynamic_submission(): void {
        $draftitemid = file_get_submitted_draft_itemid('picture');
        file_prepare_draft_area($draftitemid, null, null, null, null);
        $this->set_data(['courseid' => $this->get_course()->id, 'picture' => $draftitemid]);
    }

    /**
     * No name field; the picture and its description are required.
     *
     * @return bool
     */
    protected function name_is_required(): bool {
        return false;
    }

    /**
     * A picture is one quick screen: no later screens.
     */
    protected function define_shared_steps(): void {
    }

    /**
     * A picture and a description of it are required.
     *
     * @param array $data submitted data
     * @return array errors
     */
    protected function own_validation(array $data): array {
        $errors = [];
        if (!self::picture_file((int) ($data['picture'] ?? 0))) {
            $errors['picture'] = get_string('error_nopicture', 'tool_wizards');
        }
        if (trim($data['alt'] ?? '') === '') {
            $errors['alt'] = get_string('error_alt', 'tool_wizards');
        }
        return $errors;
    }

    /**
     * The picture in the current user's draft area.
     *
     * @param int $draftitemid the draft area
     * @return \stored_file|null
     */
    protected static function picture_file(int $draftitemid): ?\stored_file {
        global $USER;
        if (!$draftitemid) {
            return null;
        }
        $files = get_file_storage()->get_area_files(
            \core\context\user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        );
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return $file;
            }
        }
        return null;
    }

    /**
     * The text and media area's content: the picture, sized to fit, with its caption.
     *
     * The image points into the draft area; add_moduleinfo() saves the draft files of the
     * description and rewrites the link, as the standard form's editor does.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    protected function answers_to_fields(\stdClass $data): array {
        $file = self::picture_file((int) $data->picture);
        $url = \core\url::make_draftfile_url((int) $data->picture, $file->get_filepath(), $file->get_filename());
        $alt = trim($data->alt);
        $caption = trim($data->caption ?? '');
        $img = \html_writer::empty_tag('img', ['src' => $url->out(false), 'alt' => $alt, 'class' => 'img-fluid']);
        $html = $caption === ''
            ? \html_writer::tag('p', $img)
            : \html_writer::tag('figure', $img . \html_writer::tag('figcaption', s($caption)), ['class' => 'figure']);
        return [
            'name' => $caption !== '' ? $caption : $alt,
            'introeditor' => ['text' => $html, 'format' => FORMAT_HTML, 'itemid' => (int) $data->picture],
        ];
    }
}
