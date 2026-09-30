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
 * Mini-wizard: add a page of text (mod_page).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_page extends add_module_base {
    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'page';
    }

    /**
     * The editor options mod_page uses for its content.
     *
     * @return array
     */
    protected function editor_options(): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/page/locallib.php');
        return page_get_editor_options($this->get_context_for_dynamic_submission());
    }

    /**
     * Questions: a name and the page itself.
     */
    protected function define_questions(): void {
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('add_page_intro', 'tool_wizards')));
        $this->add_name_field('page_name');
        $mform->addElement('editor', 'page', get_string('page_content', 'tool_wizards'), ['rows' => 12], $this->editor_options());
        $mform->setType('page', PARAM_RAW);
        $mform->addRule('page', get_string('required'), 'required', null, 'client');
    }

    /**
     * An empty editor with its own draft area, for images added to the page.
     */
    public function set_data_for_dynamic_submission(): void {
        $draftitemid = file_get_submitted_draft_itemid('page');
        file_prepare_draft_area($draftitemid, null, null, null, null);
        $this->set_data([
            'courseid' => $this->get_course()->id,
            'page' => ['text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid],
        ]);
    }

    /**
     * The page must have some content.
     *
     * @param array $data submitted data
     * @param array $files uploaded files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim(strip_tags($data['page']['text'] ?? '', '<img><video><audio><iframe><object>')) === '') {
            $errors['page'] = get_string('error_pagecontent', 'tool_wizards');
        }
        return $errors;
    }

    /**
     * The module form fields. mod_page's own form submits its content as the "page" editor,
     * and page_add_instance() saves its images from that draft area.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    protected function answers_to_fields(\stdClass $data): array {
        return ['name' => $data->name, 'page' => $data->page];
    }
}
