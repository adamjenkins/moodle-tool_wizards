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

use core_form\dynamic_form;
use tool_wizards\local\module_creator;
use tool_wizards\local\module_types;
use tool_wizards\local\prompt;

/**
 * A mini-wizard: the few questions needed to add one kind of first content.
 *
 * Opened in a modal from the first-content suggestions. Each subclass asks its
 * essentials and turns them into the module form's own field shapes; everything
 * else is the module's own default (see {@see module_creator}).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class add_module_base extends dynamic_form {
    /** @var \stdClass|null the course, once loaded */
    protected ?\stdClass $course = null;

    /**
     * The mini-wizard type (see module_types).
     *
     * @return string
     */
    abstract protected static function get_type(): string;

    /**
     * Add the type's own questions.
     */
    abstract protected function define_questions(): void;

    /**
     * Turn the submitted answers into module form fields.
     *
     * @param \stdClass $data submitted data
     * @return array field => value in the module form's shape
     */
    abstract protected function answers_to_fields(\stdClass $data): array;

    /**
     * Form definition: the course, then the type's questions.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $this->define_questions();
    }

    /**
     * The course the content goes into, from the submitted course id.
     *
     * @return \stdClass
     */
    protected function get_course(): \stdClass {
        if ($this->course === null) {
            $this->course = get_course($this->optional_param('courseid', 0, PARAM_INT));
        }
        return $this->course;
    }

    /**
     * The course context.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \core\context\course::instance($this->get_course()->id);
    }

    /**
     * Only people who may add this kind of content here may use the form.
     *
     * The course id comes from the browser, so everything is checked against it again.
     */
    protected function check_access_for_dynamic_submission(): void {
        $course = $this->get_course();
        require_login($course, false, null, false, true);
        require_capability('moodle/course:manageactivities', $this->get_context_for_dynamic_submission());
        if (!module_types::is_available($course, static::get_type())) {
            throw new \moodle_exception('error_notavailable', 'tool_wizards');
        }
    }

    /**
     * Create the content.
     *
     * @return array ['cmid' => int, 'name' => string]
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $course = $this->get_course();
        $cm = module_creator::create($course, static::get_type(), $this->answers_to_fields($data));
        $this->after_create($cm, $data);
        prompt::mark_just_added($course->id, $cm->id);
        return ['cmid' => $cm->id, 'name' => $cm->get_formatted_name()];
    }

    /**
     * Hook for a type that needs to do more once the module exists.
     *
     * @param \cm_info $cm the new module
     * @param \stdClass $data submitted data
     */
    protected function after_create(\cm_info $cm, \stdClass $data): void {
    }

    /**
     * Initial values.
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(['courseid' => $this->get_course()->id]);
    }

    /**
     * The course page.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->get_course()->id]);
    }

    /**
     * Add the usual name field.
     *
     * @param string $label the label string key in tool_wizards
     * @param bool $required whether a name must be given
     */
    protected function add_name_field(string $label, bool $required = true): void {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string($label, 'tool_wizards'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        if ($required) {
            $mform->addRule('name', get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * Add a short plain description field.
     *
     * @param string $label the label string key in tool_wizards
     */
    protected function add_description_field(string $label): void {
        $mform = $this->_form;
        $mform->addElement('textarea', 'description', get_string($label, 'tool_wizards'), ['rows' => 4, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);
    }

    /**
     * The module form's description field for a plain description.
     *
     * @param string $text the description
     * @return array
     */
    protected static function description_editor(string $text): array {
        $text = trim($text);
        $html = $text === '' ? '' : '<p>' . nl2br(s($text), false) . '</p>';
        return ['text' => $html, 'format' => FORMAT_HTML];
    }

    /**
     * Server-side checks every form shares.
     *
     * @param array $data submitted data
     * @param array $files uploaded files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($this->_form->elementExists('name') && $this->name_is_required() && trim($data['name'] ?? '') === '') {
            $errors['name'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Whether this form insists on a name.
     *
     * @return bool
     */
    protected function name_is_required(): bool {
        return true;
    }
}
