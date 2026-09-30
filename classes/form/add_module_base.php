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
 * A mini-wizard: a few small screens of questions to add one kind of content.
 *
 * Opened in a modal from the first-content suggestions. The first screen asks the
 * essentials (a name, and what the content is for); later screens each cover one
 * theme, such as timing or feedback, so no screen holds more than a few decisions.
 * "Enough questions, let's go" is offered from the second screen on and accepts
 * what the later screens already hold: the choices that suit the chosen purpose
 * (its preset), or else the module's own defaults.
 *
 * All the screens are one form, shown a screen at a time by tool_wizards/modal_stepper.
 * Each subclass turns the answers into the module form's own field shapes; everything
 * else is the module's own default (see {@see module_creator}).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class add_module_base extends dynamic_form {
    /** @var string Completion choice: leave completion to the course's usual setting. */
    const COMPLETION_DEFAULT = 'default';

    /** @var string Completion choice: no completion. */
    const COMPLETION_NONE = 'none';

    /** @var string Completion choice: students mark it done themselves. */
    const COMPLETION_MANUAL = 'manual';

    /** @var \stdClass|null the course, once loaded */
    protected ?\stdClass $course = null;

    /** @var string[] the steps this form shows, in order */
    protected array $steps = [];

    /**
     * The mini-wizard type (see module_types).
     *
     * @return string
     */
    abstract protected static function get_type(): string;

    /**
     * Add the type's essential questions (the first screen, apart from the purpose).
     */
    abstract protected function define_questions(): void;

    /**
     * Turn the type's own answers into module form fields.
     *
     * The shared screens (visibility, groups, completion) are added by {@see self::shared_fields()}.
     *
     * @param \stdClass $data submitted data
     * @return array field => value in the module form's shape
     */
    abstract protected function answers_to_fields(\stdClass $data): array;

    /**
     * What the content can be for: the purpose cards on the first screen, in order.
     *
     * The strings are <type>_purpose_<key> and <type>_purpose_<key>_desc, and the
     * picture is pix/purpose/<type>_<key>.svg.
     *
     * @return string[] purpose keys offered in this course; none means no purpose question
     */
    protected function purposes(): array {
        return [];
    }

    /**
     * The answers that suit each purpose, for the later screens.
     *
     * @return array purpose => [control name => value]
     */
    public static function presets(): array {
        return [];
    }

    /**
     * The type's own screens after the first, in order. Each is added by define_step_<id>(),
     * titled by the strings <type>_step_<id> (short theme) and <type>_step_<id>_q (the question).
     *
     * @return string[]
     */
    protected function type_steps(): array {
        return [];
    }

    /**
     * Whether one of the type's own screens applies here.
     *
     * @param string $step the step id
     * @return bool
     */
    protected function type_step_applies(string $step): bool {
        return true;
    }

    /**
     * The automatic completion choices this type offers, besides none and manual.
     *
     * @return array key => ['label' => string, 'fields' => module form fields]
     */
    protected function completion_choices(): array {
        return [];
    }

    /**
     * Which answer a module form field comes from, for showing core's validation errors
     * on the right screen. Checked as a prefix of the error's field name.
     *
     * @return array module form field => wizard control
     */
    protected function error_owners(): array {
        return [];
    }

    /**
     * Form definition: the course, the first screen, the type's screens, then the shared ones.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->steps = [];
        $this->open_step('essentials', get_string('step_essentials', 'tool_wizards'), null);
        $mform->addElement('static', 'wizarderrors', '', '');
        $this->define_questions();
        $this->add_purpose_cards();
        $this->close_step();

        foreach ($this->type_steps() as $step) {
            if (!$this->type_step_applies($step)) {
                continue;
            }
            $type = static::get_type();
            $this->open_step(
                $step,
                get_string($type . '_step_' . $step, 'tool_wizards'),
                get_string($type . '_step_' . $step . '_q', 'tool_wizards')
            );
            $this->{'define_step_' . $step}();
            $this->close_step();
        }
        $this->define_shared_steps();

        $mform->addElement('html', $this->navigation_html());
    }

    /**
     * Render the form, with the stepper that shows it a screen at a time.
     *
     * The stepper is asked for here, not in the definition: the modal collects a form's
     * JavaScript only while rendering it (core_form\external\dynamic_form::execute()).
     *
     * @return string HTML
     */
    public function render() {
        global $PAGE;
        $PAGE->requires->js_call_amd('tool_wizards/modal_stepper', 'init', ['.tool_wizards-miniwizard-' . static::get_type()]);
        return parent::render();
    }

    /**
     * Start a screen.
     *
     * @param string $id the step id
     * @param string $title the short theme name shown in the progress list
     * @param string|null $question the question the screen asks, as its heading
     */
    protected function open_step(string $id, string $title, ?string $question): void {
        $this->steps[] = $id;
        $html = \html_writer::start_tag('fieldset', [
            'class' => 'tool_wizards-step',
            'data-step' => $id,
            'data-title' => $title,
        ]);
        $html .= \html_writer::tag('legend', s($question ?? $title), ['class' => 'h5 mb-3', 'tabindex' => '-1']);
        $this->_form->addElement('html', $html);
    }

    /**
     * End a screen.
     */
    protected function close_step(): void {
        $this->_form->addElement('html', \html_writer::end_tag('fieldset'));
    }

    /**
     * The purpose cards: pictures of what the content can be for, one to choose.
     */
    protected function add_purpose_cards(): void {
        $purposes = $this->purposes();
        if (!$purposes) {
            return;
        }
        $type = static::get_type();
        $mform = $this->_form;
        $cards = [];
        foreach ($purposes as $purpose) {
            $cards[] = $mform->createElement('radio', 'purpose', '', self::card_html(
                self::illustration($type . '_' . $purpose),
                get_string($type . '_purpose_' . $purpose, 'tool_wizards'),
                get_string($type . '_purpose_' . $purpose . '_desc', 'tool_wizards')
            ), $purpose, ['class' => 'tool_wizards-cardinput']);
        }
        $mform->addGroup($cards, 'purposegroup', get_string($type . '_purpose', 'tool_wizards'), '', false);
        $required = [get_string('error_purpose', 'tool_wizards'), 'required', null, 'client'];
        $mform->addGroupRule('purposegroup', ['purpose' => [$required]]);
        $mform->setType('purpose', PARAM_ALPHANUMEXT);
    }

    /**
     * A choice card's label: a picture, a title and a line saying when to choose it.
     *
     * @param string $svg the inline picture, or ''
     * @param string $title the title
     * @param string $desc when to choose it
     * @return string HTML
     */
    protected static function card_html(string $svg, string $title, string $desc): string {
        return \html_writer::span(
            ($svg !== '' ? \html_writer::span($svg, 'tool_wizards-cardpicture', ['aria-hidden' => 'true']) : '') .
            \html_writer::span(
                \html_writer::tag('strong', s($title), ['class' => 'd-block']) .
                \html_writer::span(s($desc), 'small text-body-secondary'),
                'tool_wizards-cardtext'
            ),
            'tool_wizards-card'
        );
    }

    /**
     * A picture from pix/purpose, inline so it follows the theme's colours.
     *
     * @param string $name the file name without .svg
     * @return string the SVG markup, or '' if there is none
     */
    protected static function illustration(string $name): string {
        global $CFG;
        $file = $CFG->dirroot . '/admin/tool/wizards/pix/purpose/' . clean_param($name, PARAM_ALPHANUMEXT) . '.svg';
        return is_readable($file) ? trim(file_get_contents($file)) : '';
    }

    /**
     * A choice's title and explanation: the strings <key> and <key>_desc.
     *
     * @param string $key the string key in tool_wizards
     * @return string[] [title, explanation]
     */
    protected static function choice(string $key): array {
        return [get_string($key, 'tool_wizards'), get_string($key . '_desc', 'tool_wizards')];
    }

    /**
     * Radio choices, one per line, each with a line explaining it.
     *
     * @param string $name the control name
     * @param string $label the group label
     * @param array $choices value => [title, explanation]
     */
    protected function add_choices(string $name, string $label, array $choices): void {
        $mform = $this->_form;
        $radios = [];
        foreach ($choices as $value => [$title, $desc]) {
            $radios[] = $mform->createElement(
                'radio',
                $name,
                '',
                self::card_html('', $title, $desc),
                $value,
                ['class' => 'tool_wizards-cardinput']
            );
        }
        $mform->addGroup($radios, $name . 'group', $label, '', false);
    }

    /**
     * The shared screens: visibility, groups and completion, each only where it can matter.
     */
    protected function define_shared_steps(): void {
        $mform = $this->_form;
        $course = $this->get_course();
        $context = $this->get_context_for_dynamic_submission();
        $modname = module_types::modname(static::get_type());

        if (has_capability('moodle/course:activityvisibility', $context)) {
            $this->open_step(
                'visibility',
                get_string('step_visibility', 'tool_wizards'),
                get_string('step_visibility_q', 'tool_wizards')
            );
            $this->add_choices('visible', get_string('step_visibility', 'tool_wizards'), [
                1 => self::choice('visible_show'),
                0 => self::choice('visible_hide'),
            ]);
            $mform->setDefault('visible', 1);
            $mform->setType('visible', PARAM_INT);
            $this->close_step();
        }

        if ($this->groups_apply($course, $modname)) {
            $this->open_step('groups', get_string('step_groups', 'tool_wizards'), get_string('step_groups_q', 'tool_wizards'));
            $this->add_choices('groupmode', get_string('step_groups', 'tool_wizards'), [
                NOGROUPS => self::choice('groups_none'),
                SEPARATEGROUPS => self::choice('groups_separate'),
                VISIBLEGROUPS => self::choice('groups_visible'),
            ]);
            $mform->setDefault('groupmode', (int) $course->groupmode);
            $mform->setType('groupmode', PARAM_INT);
            $this->close_step();
        }

        if ($this->completion_applies($course, $modname)) {
            $this->open_step(
                'completion',
                get_string('step_completion', 'tool_wizards'),
                get_string('step_completion_q', 'tool_wizards')
            );
            $choices = [
                self::COMPLETION_DEFAULT => self::choice('completion_default'),
                self::COMPLETION_NONE => self::choice('completion_none'),
                self::COMPLETION_MANUAL => self::choice('completion_manual'),
            ];
            foreach ($this->completion_choices() as $key => $choice) {
                $choices[$key] = [$choice['label'], $choice['desc']];
            }
            $this->add_choices('completionchoice', get_string('step_completion', 'tool_wizards'), $choices);
            $mform->setDefault('completionchoice', self::COMPLETION_DEFAULT);
            $mform->setType('completionchoice', PARAM_ALPHANUMEXT);
            $this->close_step();
        }
    }

    /**
     * Whether to ask about groups: the course has groups, does not force a group mode,
     * and the module supports groups.
     *
     * @param \stdClass $course the course
     * @param string $modname the module
     * @return bool
     */
    protected function groups_apply(\stdClass $course, string $modname): bool {
        return !$course->groupmodeforce && plugin_supports('mod', $modname, FEATURE_GROUPS, false)
            && groups_get_all_groups($course->id, 0, 0, 'g.id');
    }

    /**
     * Whether to ask about completion: the site and course track it and the module supports it.
     *
     * @param \stdClass $course the course
     * @param string $modname the module
     * @return bool
     */
    protected function completion_applies(\stdClass $course, string $modname): bool {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        return (new \completion_info($course))->is_enabled() && plugin_supports('mod', $modname, FEATURE_COMPLETION, true);
    }

    /**
     * The module form fields for the shared screens.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    protected function shared_fields(\stdClass $data): array {
        $fields = [];
        if (isset($data->visible) && in_array('visibility', $this->steps, true)) {
            $fields['visible'] = (int) $data->visible;
        }
        if (isset($data->groupmode) && in_array('groups', $this->steps, true)) {
            $fields['groupmode'] = (int) $data->groupmode;
        }
        $choice = $data->completionchoice ?? self::COMPLETION_DEFAULT;
        if (in_array('completion', $this->steps, true) && $choice !== self::COMPLETION_DEFAULT) {
            $automatic = $this->completion_choices();
            if ($choice === self::COMPLETION_NONE) {
                $fields['completion'] = COMPLETION_TRACKING_NONE;
            } else if ($choice === self::COMPLETION_MANUAL) {
                $fields['completion'] = COMPLETION_TRACKING_MANUAL;
            } else if (isset($automatic[$choice])) {
                $fields = array_replace($fields, ['completion' => COMPLETION_TRACKING_AUTOMATIC], $automatic[$choice]['fields']);
            }
        }
        return $fields;
    }

    /**
     * All the module form fields for the answers.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    public function fields_for(\stdClass $data): array {
        return array_replace($this->shared_fields($data), $this->answers_to_fields($data));
    }

    /**
     * Back, Next, "Enough questions, let's go", and the presets for the stepper.
     *
     * @return string HTML
     */
    protected function navigation_html(): string {
        $buttons = \html_writer::tag(
            'button',
            get_string('back', 'tool_wizards'),
            ['type' => 'button', 'class' => 'btn btn-secondary', 'data-wizard' => 'back']
        ) .
            \html_writer::tag(
                'button',
                get_string('letsgo', 'tool_wizards'),
                ['type' => 'button', 'class' => 'btn btn-outline-primary', 'data-wizard' => 'go']
            ) .
            \html_writer::tag(
                'button',
                get_string('next', 'tool_wizards'),
                ['type' => 'button', 'class' => 'btn btn-primary', 'data-wizard' => 'next']
            ) .
            \html_writer::tag(
                'button',
                get_string('add', 'core'),
                ['type' => 'button', 'class' => 'btn btn-primary', 'data-wizard' => 'add']
            );
        return \html_writer::div($buttons, 'tool_wizards-stepnav d-flex flex-wrap gap-2 justify-content-end mt-3', [
            'data-region' => 'tool_wizards-stepnav',
            'data-presets' => json_encode((object) static::presets()),
            'data-progresslabel' => get_string('progress', 'tool_wizards'),
        ]);
    }

    /**
     * Wrap the form so the stepper can find it.
     */
    public function definition_after_data() {
        $this->_form->updateAttributes(['class' => trim(($this->_form->getAttribute('class') ?? '') .
            ' tool_wizards-miniwizard tool_wizards-miniwizard-' . static::get_type())]);
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
        $cm = module_creator::create($course, static::get_type(), $this->fields_for($data));
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
        $attributes = ['size' => 40] + ($required ? ['data-wizard-required' => 1] : []);
        $mform->addElement('text', 'name', get_string($label, 'tool_wizards'), $attributes);
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
        $mform->addElement('textarea', 'description', get_string($label, 'tool_wizards'), ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);
    }

    /**
     * "Out of how many points?", shown unless grading is switched off.
     *
     * @param string $controller the control that switches grading on or off
     * @param string $offvalue its value for "no grades"
     */
    protected function add_max_grade_field(string $controller, string $offvalue): void {
        $mform = $this->_form;
        $mform->addElement('text', 'maxgrade', get_string('maxgrade', 'tool_wizards'), ['size' => 4]);
        $mform->setType('maxgrade', PARAM_RAW_TRIMMED);
        $mform->setDefault('maxgrade', 10);
        $mform->hideIf('maxgrade', $controller, 'eq', $offvalue);
    }

    /**
     * Check the maximum grade when grading is on: a whole number from 1 to the site's maximum.
     *
     * @param array $data submitted data
     * @param string $controller the control that switches grading on or off
     * @param string $offvalue its value for "no grades"
     * @return array errors
     */
    protected function max_grade_errors(array $data, string $controller, string $offvalue): array {
        global $CFG;
        if (($data[$controller] ?? $offvalue) === $offvalue) {
            return [];
        }
        $max = $data['maxgrade'] ?? '';
        $limit = (int) ($CFG->gradepointmax ?? 100);
        if (!preg_match('/^\d+$/', (string) $max) || (int) $max < 1 || (int) $max > $limit) {
            return ['maxgrade' => get_string('error_maxgrade', 'tool_wizards', $limit)];
        }
        return [];
    }

    /**
     * A date as a date_time_selector posts it, in the user's timezone.
     *
     * @param int $timestamp the time, or 0 for none
     * @return array the element's fields; ['enabled' => 0] for none
     */
    protected static function date_fields(int $timestamp): array {
        if (!$timestamp) {
            return ['enabled' => 0];
        }
        $date = usergetdate($timestamp);
        return [
            'enabled' => 1,
            'day' => $date['mday'],
            'month' => $date['mon'],
            'year' => $date['year'],
            'hour' => $date['hours'],
            'minute' => $date['minutes'],
        ];
    }

    /**
     * A duration as an optional duration element posts it.
     *
     * @param int $seconds the duration, or 0 for none
     * @return array the element's fields; ['enabled' => 0] for none
     */
    protected static function duration_fields(int $seconds): array {
        if ($seconds <= 0) {
            return ['enabled' => 0];
        }
        foreach ([WEEKSECS, DAYSECS, HOURSECS, MINSECS, 1] as $unit) {
            if ($seconds % $unit === 0) {
                return ['enabled' => 1, 'number' => $seconds / $unit, 'timeunit' => $unit];
            }
        }
        return ['enabled' => 1, 'number' => $seconds, 'timeunit' => 1];
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
     * Server-side checks: the wizard's own, then core's for the module it will create,
     * each shown on the screen of the answer it concerns.
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
        if ($this->purposes() && !in_array($data['purpose'] ?? '', $this->purposes(), true)) {
            $errors['purposegroup'] = get_string('error_purpose', 'tool_wizards');
        }
        $errors = array_merge($errors, $this->own_validation($data));
        if ($errors) {
            return $errors;
        }
        $moduleerrors = module_creator::check($this->get_course(), static::get_type(), $this->fields_for((object) $data));
        return $this->place_module_errors($moduleerrors);
    }

    /**
     * The type's own checks of its answers.
     *
     * @param array $data submitted data
     * @return array errors
     */
    protected function own_validation(array $data): array {
        return [];
    }

    /**
     * Show core's errors next to the answers they concern, or at the top of the first screen.
     *
     * @param array $moduleerrors module form field => message
     * @return array wizard control => message
     */
    protected function place_module_errors(array $moduleerrors): array {
        $errors = [];
        $general = [];
        $owners = $this->error_owners() + [
            'visible' => 'visiblegroup',
            'groupmode' => 'groupmodegroup',
            'completion' => 'completionchoicegroup',
            'name' => 'name',
        ];
        foreach ($moduleerrors as $field => $message) {
            $owner = null;
            foreach ($owners as $prefix => $control) {
                if (str_starts_with((string) $field, $prefix) && $this->_form->elementExists($control)) {
                    $owner = $control;
                    break;
                }
            }
            if ($owner === null) {
                $general[] = $message;
            } else {
                $errors[$owner] = isset($errors[$owner]) ? $errors[$owner] . ' ' . $message : $message;
            }
        }
        if ($general) {
            $errors['wizarderrors'] = implode(' ', $general);
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
