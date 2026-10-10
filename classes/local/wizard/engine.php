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

namespace tool_wizards\local\wizard;

use tool_wizards\local\course_creator;
use tool_wizards\local\module_creator;

/**
 * Runs one wizard definition for one course (activity wizards) or category (course wizards).
 *
 * It expands the shared screens, settles every condition that does not depend on the
 * teacher's answers (course facts, site facts, capabilities, what the target form offers),
 * reads and checks the answers, turns them into the target form's fields, and places the
 * target form's own errors on the questions that set those fields.
 *
 * Conditions on answers are evaluated here for the submitted answers, and in the browser by
 * tool_wizards/modal_stepper as the teacher goes; {@see self::client_condition()} gives the
 * browser the same condition with everything else already decided.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {
    /** @var array the definition */
    protected array $doc;

    /** @var int the wizard's id, for its pictures (0 for none) */
    protected int $wizardid;

    /** @var \stdClass|null the course an activity wizard adds to */
    protected ?\stdClass $course;

    /** @var int the category a course wizard starts in */
    protected int $categoryid;

    /** @var array|null the expanded screens, once built */
    protected ?array $screens = null;

    /** @var array|null field => offered values in the target form, once read */
    protected ?array $offered = null;

    /** @var \cm_info|null the activity, for an in-activity wizard */
    protected ?\cm_info $cm = null;

    /**
     * Constructor.
     *
     * @param array $doc a valid definition
     * @param int $wizardid the wizard's id (0 for an unsaved one)
     * @param \stdClass|null $course the course, for an activity wizard
     * @param int $categoryid the starting category, for a course wizard
     */
    public function __construct(array $doc, int $wizardid = 0, ?\stdClass $course = null, int $categoryid = 0) {
        $this->doc = $doc;
        $this->wizardid = $wizardid;
        $this->course = $course;
        $this->categoryid = $categoryid;
    }

    /**
     * Run an in-activity wizard on this activity (its course becomes the wizard's course).
     *
     * @param \cm_info $cm the activity
     * @return self
     */
    public function set_cm(\cm_info $cm): self {
        $this->cm = $cm;
        $this->course = get_course($cm->course);
        $this->offered = null;
        $this->screens = null;
        return $this;
    }

    /**
     * The activity an in-activity wizard adds to.
     *
     * @return \cm_info|null
     */
    public function cm(): ?\cm_info {
        return $this->cm;
    }

    /**
     * Whether this is an in-activity wizard.
     *
     * @return bool
     */
    public function is_content_wizard(): bool {
        return ($this->doc['target']['type'] ?? '') === 'content';
    }

    /**
     * Whether this is a course wizard.
     *
     * @return bool
     */
    public function is_course_wizard(): bool {
        return ($this->doc['target']['type'] ?? '') === 'course';
    }

    /**
     * The module an activity wizard adds.
     *
     * @return string
     */
    public function modname(): string {
        return (string) ($this->doc['target']['modname'] ?? '');
    }

    /**
     * The definition.
     *
     * @return array
     */
    public function definition(): array {
        return $this->doc;
    }

    /**
     * The wizard's id.
     *
     * @return int
     */
    public function wizardid(): int {
        return $this->wizardid;
    }

    /**
     * The context conditions and capabilities are checked in.
     *
     * @return \context
     */
    public function context(): \context {
        if ($this->cm) {
            return $this->cm->context;
        }
        if ($this->course) {
            return \core\context\course::instance($this->course->id);
        }
        return \core\context\coursecat::instance($this->categoryid ?: \core_course_category::get_default()->id);
    }

    /**
     * The course, for an activity wizard.
     *
     * @return \stdClass|null
     */
    public function course(): ?\stdClass {
        return $this->course;
    }

    /**
     * The screens, with shared screens expanded and everything that does not depend on the
     * answers already decided: screens, questions and choices whose condition is false here
     * are gone, and the rest carry their condition for the browser ("cond", or null).
     *
     * @return array list of screens: key, title, question, cond, items
     */
    public function screens(): array {
        if ($this->screens !== null) {
            return $this->screens;
        }
        $screens = [];
        foreach ($this->doc['screens'] as $screen) {
            if (isset($screen['use'])) {
                $screen = shared::expand($screen, $this);
                if ($screen === null) {
                    continue;
                }
            }
            $cond = $this->client_condition($screen['when'] ?? null);
            if ($cond === false) {
                continue;
            }
            $items = [];
            foreach ($screen['items'] as $i => $item) {
                $itemcond = $this->client_condition($item['when'] ?? null);
                if ($itemcond === false) {
                    continue;
                }
                $item['cond'] = $itemcond === true ? null : $itemcond;
                if (empty($item['key'])) {
                    $item['key'] = 'hint_' . $screen['key'] . '_' . $i;
                }
                if (in_array($item['kind'], validator::CHOICE_KINDS, true)) {
                    $item['choices'] = $this->choices_for($item);
                    if (!$item['choices']) {
                        continue;
                    }
                }
                $items[] = $item;
            }
            if (!$items) {
                continue;
            }
            $screen['items'] = $items;
            $screen['cond'] = $cond === true ? null : $cond;
            $screens[] = $screen;
        }
        $this->screens = $screens;
        return $screens;
    }

    /**
     * Every question on the screens, by key.
     *
     * @return array key => item, with its screen key as "screen"
     */
    public function questions(): array {
        $out = [];
        foreach ($this->screens() as $screen) {
            foreach ($screen['items'] as $item) {
                if (!in_array($item['kind'], ['hint', 'review'], true)) {
                    $item['screen'] = $screen['key'];
                    $item['screencond'] = $screen['cond'];
                    $out[$item['key']] = $item;
                }
            }
        }
        return $out;
    }

    /**
     * A choice question's choices here, with their conditions settled as far as possible.
     *
     * @param array $item the question
     * @return array choices: value, title, desc, picture, cond, preset, sets, flags
     */
    protected function choices_for(array $item): array {
        if (($item['choicesfrom'] ?? '') === 'course:formats') {
            $choices = [];
            foreach (course_creator::get_formats() as $format) {
                $flags = [];
                foreach (['usessections', 'usesstartdate'] as $flag) {
                    if (!empty($format[$flag])) {
                        $flags[] = $flag;
                    }
                }
                $choices[] = ['value' => $format['name'], 'title' => $format['label'], 'desc' => $format['description'],
                    'flags' => $flags, 'cond' => null];
            }
            return $choices;
        }
        if (($item['choicesfrom'] ?? '') === 'course:ltitools') {
            $choices = [];
            foreach (module_creator::lti_tools($this->course) as $tool) {
                $choices[] = ['value' => (int) $tool->id, 'title' => format_string($tool->name),
                    'desc' => shorten_text(format_string($tool->description ?? ''), 150), 'cond' => null];
            }
            return $choices;
        }
        $choices = [];
        foreach ($item['choices'] ?? [] as $choice) {
            $cond = $this->client_condition($choice['when'] ?? null);
            if ($cond === false) {
                continue;
            }
            $choice['cond'] = $cond === true ? null : $cond;
            $choices[] = $choice;
        }
        return $choices;
    }

    /**
     * A condition with everything that does not depend on the answers decided.
     *
     * @param array|null $cond the condition
     * @return bool|array true, false, or the remaining condition on answers
     */
    public function client_condition(?array $cond) {
        if ($cond === null) {
            return true;
        }
        if (isset($cond['all']) || isset($cond['any'])) {
            $all = isset($cond['all']);
            $parts = [];
            foreach ($cond[$all ? 'all' : 'any'] as $part) {
                $value = $this->client_condition($part);
                if ($value === ($all ? false : true)) {
                    return $value;
                }
                if (is_array($value)) {
                    $parts[] = $value;
                }
            }
            if (!$parts) {
                return $all;
            }
            return count($parts) === 1 ? $parts[0] : [$all ? 'all' : 'any' => $parts];
        }
        if (array_key_exists('not', $cond) && !isset($cond['answer'])) {
            $value = $this->client_condition($cond['not']);
            return is_bool($value) ? !$value : ['not' => $value];
        }
        if (isset($cond['answer'])) {
            return $cond;
        }
        return $this->fact($cond);
    }

    /**
     * Decide a condition that does not depend on the answers.
     *
     * @param array $cond the condition
     * @return bool
     */
    protected function fact(array $cond): bool {
        global $CFG;
        if (isset($cond['course'])) {
            $course = $this->course;
            if (!$course) {
                return false;
            }
            switch ($cond['course']) {
                case 'hasgroups':
                    return (bool) groups_get_all_groups($course->id, 0, 0, 'g.id');
                case 'completion':
                    require_once($CFG->libdir . '/completionlib.php');
                    return (new \completion_info($course))->is_enabled() && $this->module_supports(FEATURE_COMPLETION, true);
                case 'groupmodeforce':
                    return !empty($course->groupmodeforce);
                case 'forcedgroupmode':
                    return !empty($course->groupmodeforce) && (int) $course->groupmode === (int) ($cond['is'] ?? -1);
            }
            return false;
        }
        if (isset($cond['site'])) {
            switch ($cond['site']) {
                case 'comments':
                    return !empty($CFG->usecomments);
                case 'filter':
                    return filter_is_enabled((string) $cond['name']);
                case 'qtype':
                    require_once($CFG->libdir . '/questionlib.php');
                    return \question_bank::qtype_enabled((string) $cond['name']);
                case 'showstartedcourses':
                    return course_creator::show_started_courses_enabled();
                case 'completion':
                    return !empty($CFG->enablecompletion);
                case 'categorychoice':
                    return count(course_creator::get_categories()) > 1;
            }
            return false;
        }
        if (isset($cond['capability'])) {
            foreach ((array) $cond['capability'] as $cap) {
                if ($this->is_course_wizard()) {
                    if (!guess_if_creator_will_have_course_capability($cap, $this->context())) {
                        return false;
                    }
                } else if (!has_capability($cap, $this->context())) {
                    return false;
                }
            }
            return true;
        }
        if (isset($cond['offered'])) {
            $values = $this->offered()[$cond['offered']['field']] ?? null;
            return $values !== null && in_array((string) $cond['offered']['value'], $values, true);
        }
        return false;
    }

    /**
     * Whether the target module supports a feature.
     *
     * @param string $feature a FEATURE_ constant
     * @param mixed $default the default
     * @return bool
     */
    public function module_supports(string $feature, $default = false): bool {
        return !$this->is_course_wizard() && !$this->is_content_wizard()
            && (bool) plugin_supports('mod', $this->modname(), $feature, $default);
    }

    /**
     * The values the target form offers for each choice field (select options, radio values).
     *
     * @return array field => string[]
     */
    public function offered(): array {
        if ($this->offered === null) {
            $this->offered = [];
            if ($this->is_content_wizard()) {
                $class = extensions::content_for($this->doc);
                if ($class && $this->cm) {
                    $this->offered = $class::offered($this->cm);
                }
            } else if (!$this->is_course_wizard() && $this->course) {
                $form = module_creator::form($this->course, $this->modname());
                $this->offered = \tool_wizards\local\form_submission::offered_values($form);
            }
        }
        return $this->offered;
    }

    /**
     * Evaluate a condition for the answers given.
     *
     * @param mixed $cond a condition, or a client condition (true, false, array)
     * @param array $answers question key => answer
     * @param array $flags question key => flags of the chosen choice
     * @return bool
     */
    public static function holds($cond, array $answers, array $flags = []): bool {
        if ($cond === null || $cond === true) {
            return true;
        }
        if ($cond === false) {
            return false;
        }
        if (isset($cond['all'])) {
            foreach ($cond['all'] as $part) {
                if (!self::holds($part, $answers, $flags)) {
                    return false;
                }
            }
            return true;
        }
        if (isset($cond['any'])) {
            foreach ($cond['any'] as $part) {
                if (self::holds($part, $answers, $flags)) {
                    return true;
                }
            }
            return false;
        }
        if (array_key_exists('not', $cond) && !isset($cond['answer'])) {
            return !self::holds($cond['not'], $answers, $flags);
        }
        if (isset($cond['answer'])) {
            $value = $answers[$cond['answer']] ?? null;
            $str = is_scalar($value) ? (string) $value : '';
            if (array_key_exists('is', $cond)) {
                return $value !== null && $str === (string) $cond['is'];
            }
            if (array_key_exists('not', $cond)) {
                return $value === null || $str !== (string) $cond['not'];
            }
            if (isset($cond['in'])) {
                return $value !== null && in_array($str, array_map('strval', $cond['in']), true);
            }
            if (isset($cond['has'])) {
                return in_array($cond['has'], $flags[$cond['answer']] ?? [], true);
            }
            if (isset($cond['empty'])) {
                return self::is_empty($value) === (bool) $cond['empty'];
            }
        }
        return false;
    }

    /**
     * Whether an answer is empty.
     *
     * @param mixed $value the answer
     * @return bool
     */
    public static function is_empty($value): bool {
        if (is_array($value)) {
            return trim(strip_tags((string) ($value['text'] ?? ''), '<img><video><audio><iframe><object>')) === '';
        }
        return $value === null || $value === '' || (is_string($value) && trim($value) === '');
    }

    /**
     * A question's default value here.
     *
     * @param array $item the question
     * @return mixed
     */
    public function default_for(array $item) {
        $default = $item['default'] ?? null;
        if (is_string($default) && preg_match('/^config:([a-z][a-z0-9_]*)\/([a-z0-9_]+)$/', $default, $m)) {
            $plugin = $m[1] === 'core' ? 'core' : $m[1];
            return get_config($plugin, $m[2]);
        }
        if ($default === 'course:groupmode') {
            return $this->course ? (int) $this->course->groupmode : 0;
        }
        return $default;
    }

    /**
     * Read the answers from submitted form data, typed by question kind.
     *
     * Only questions that are active for these answers are kept: a question on a screen,
     * or with a condition, that does not apply is treated as not asked.
     *
     * @param array $data the submitted data (as validation() receives it)
     * @return array question key => answer
     */
    public function answers(array $data): array {
        $raw = [];
        foreach ($this->questions() as $key => $item) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $raw[$key] = $this->typed($item, $data[$key]);
        }
        // Drop what does not apply, until nothing changes (a condition may depend on a dropped answer).
        do {
            $changed = false;
            $flags = $this->flags($raw);
            foreach ($this->questions() as $key => $item) {
                if (!array_key_exists($key, $raw)) {
                    continue;
                }
                if (
                    !self::holds($item['screencond'], $raw, $flags) || !self::holds($item['cond'], $raw, $flags)
                        || !$this->choice_allowed($item, $raw[$key], $raw, $flags)
                ) {
                    unset($raw[$key]);
                    $changed = true;
                }
            }
        } while ($changed);
        return $raw;
    }

    /**
     * Whether an answer to a choice question is one of its available choices.
     *
     * @param array $item the question
     * @param mixed $value the answer
     * @param array $answers all answers
     * @param array $flags chosen-choice flags
     * @return bool
     */
    protected function choice_allowed(array $item, $value, array $answers, array $flags): bool {
        if (!in_array($item['kind'], validator::CHOICE_KINDS, true)) {
            return true;
        }
        foreach ($item['choices'] as $choice) {
            if ((string) $choice['value'] === (string) $value) {
                return self::holds($choice['cond'], $answers, $flags);
            }
        }
        return false;
    }

    /**
     * The flags of each chosen choice (for "has" conditions).
     *
     * @param array $answers question key => answer
     * @return array question key => string[]
     */
    public function flags(array $answers): array {
        $flags = [];
        foreach ($this->questions() as $key => $item) {
            if (!isset($answers[$key]) || empty($item['choices'])) {
                continue;
            }
            foreach ($item['choices'] as $choice) {
                if ((string) $choice['value'] === (string) $answers[$key]) {
                    $flags[$key] = $choice['flags'] ?? [];
                }
            }
        }
        return $flags;
    }

    /**
     * A submitted value as its question's type.
     *
     * @param array $item the question
     * @param mixed $value the submitted value
     * @return mixed
     */
    protected function typed(array $item, $value) {
        switch ($item['kind']) {
            case 'yesno':
            case 'date':
            case 'duration':
            case 'file':
            case 'category':
                return (int) $value;
            case 'number':
            case 'percent':
                if (is_string($value) && trim($value) === '') {
                    return '';
                }
                $number = unformat_float((string) $value, true);
                return $number === false || $number === null ? (string) $value : $number;
            case 'editor':
                return is_array($value) ? $value : ['text' => (string) $value, 'format' => FORMAT_HTML];
            default:
                return is_scalar($value) ? trim((string) $value) : '';
        }
    }

    /**
     * Check the answers: required answers, number limits, lengths, file types, and each
     * action's own checks.
     *
     * @param array $answers question key => answer (from {@see self::answers()})
     * @return array question key => error message
     */
    public function check(array $answers): array {
        $errors = [];
        $flags = $this->flags($answers);
        foreach ($this->questions() as $key => $item) {
            if (!self::holds($item['screencond'], $answers, $flags) || !self::holds($item['cond'], $answers, $flags)) {
                continue;
            }
            $value = $answers[$key] ?? null;
            $required = $item['required'] ?? false;
            if (is_array($required)) {
                $required = self::holds($this->client_condition($required), $answers, $flags);
            }
            $empty = $item['kind'] === 'file' ? !self::first_draft_file((int) $value) : self::is_empty($value);
            if ($required && $empty) {
                $errors[$key] = isset($item['requiredmessage']) ? text::get($item['requiredmessage'])
                    : get_string('required');
                continue;
            }
            if ($empty) {
                continue;
            }
            if (in_array($item['kind'], ['number', 'percent'], true)) {
                if (!is_int($value) && !is_float($value)) {
                    $errors[$key] = get_string('err_numeric', 'form');
                    continue;
                }
                $min = $item['min'] ?? ($item['kind'] === 'percent' ? 0 : null);
                $max = $item['max'] ?? ($item['kind'] === 'percent' ? 100 : null);
                if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
                    $errors[$key] = get_string('error_range', 'tool_wizards', ['min' => $min ?? '', 'max' => $max ?? '']);
                    continue;
                }
            }
            if (isset($item['maxlength']) && is_string($value) && \core_text::strlen($value) > $item['maxlength']) {
                $errors[$key] = get_string('maximumchars', '', $item['maxlength']);
                continue;
            }
            if ($item['kind'] === 'file' && isset($item['extensions'])) {
                $rule = $item['extensions'];
                if (self::holds($this->client_condition($rule['when'] ?? null), $answers, $flags)) {
                    $file = self::first_draft_file((int) $value);
                    $ext = $file ? '.' . strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) : '';
                    if (!in_array($ext, array_map('strtolower', $rule['in']), true)) {
                        $errors[$key] = isset($rule['message']) ? text::get($rule['message'])
                            : get_string('error_extension', 'tool_wizards', implode(', ', $rule['in']));
                    }
                }
            }
        }
        foreach ($this->actions($answers) as [$class, $config]) {
            $errors += $class::validate($config, $answers, $this->context());
        }
        return $errors;
    }

    /**
     * The actions that apply to these answers.
     *
     * @param array $answers question key => answer
     * @return array of [class, config]
     */
    public function actions(array $answers): array {
        $out = [];
        $flags = $this->flags($answers);
        $registered = extensions::actions();
        foreach ($this->doc['actions'] ?? [] as $config) {
            $class = $registered[$config['action']] ?? null;
            if ($class && self::holds($this->client_condition($config['when'] ?? null), $answers, $flags)) {
                $out[] = [$class, $config];
            }
        }
        return $out;
    }

    /**
     * The target form's fields for the answers.
     *
     * @param array $answers question key => answer (from {@see self::answers()})
     * @return array field => value, nested like $_POST
     */
    public function fields(array $answers): array {
        $fields = [];
        $flags = $this->flags($answers);
        foreach ($this->questions() as $key => $item) {
            if (!array_key_exists($key, $answers)) {
                continue;
            }
            $value = $answers[$key];
            foreach ($item['choices'] ?? [] as $choice) {
                if ((string) $choice['value'] === (string) $value && isset($choice['sets'])) {
                    foreach ($choice['sets'] as $field => $fieldvalue) {
                        self::set_field($fields, $field, $this->substitute($fieldvalue, $answers));
                    }
                }
            }
            if (!isset($item['sets'])) {
                continue;
            }
            $mappings = array_is_list($item['sets']) ? $item['sets'] : [$item['sets']];
            foreach ($mappings as $map) {
                if (!self::holds($this->client_condition($map['when'] ?? null), $answers, $flags)) {
                    continue;
                }
                if (isset($map['transform'])) {
                    $class = extensions::transforms()[$map['transform']] ?? null;
                    if ($class) {
                        $inputs = [];
                        foreach ($map['from'] ?? [] as $name => $from) {
                            $inputs[$name] = $answers[$from] ?? null;
                        }
                        foreach ($class::apply($inputs) as $field => $fieldvalue) {
                            self::set_field($fields, $field, $fieldvalue);
                        }
                    }
                    continue;
                }
                self::set_field($fields, $map['field'], $this->field_value($item, $map, $value, $answers));
            }
        }
        return $fields;
    }

    /**
     * One answer as the value of the field it sets.
     *
     * @param array $item the question
     * @param array $map the mapping
     * @param mixed $value the answer
     * @param array $answers all answers
     * @return mixed
     */
    protected function field_value(array $item, array $map, $value, array $answers) {
        if (isset($map['percentof'])) {
            $of = $map['percentof'];
            if (is_string($of) && preg_match('/^config:([a-z][a-z0-9_]*)\/([a-z0-9_]+)$/', $of, $m)) {
                $of = (float) get_config($m[1], $m[2]);
            }
            return self::is_empty($value) ? '' : format_float((float) $of * (float) $value / 100, 2, false, true);
        }
        if (isset($map['emptyfrom']) && self::is_empty($value)) {
            $file = self::first_draft_file((int) ($answers[$map['emptyfrom']['filename']] ?? 0));
            $value = $file ? pathinfo($file->get_filename(), PATHINFO_FILENAME) : get_string('file_defaultname', 'tool_wizards');
        }
        $as = $map['as'] ?? null;
        if ($as === 'editor') {
            $text = trim((string) $value);
            return ['text' => $text === '' ? '' : '<p>' . nl2br(s($text), false) . '</p>', 'format' => FORMAT_HTML];
        }
        if ($as === 'int') {
            return (int) $value;
        }
        if ($as === 'float') {
            return (float) $value;
        }
        if ($this->is_course_wizard()) {
            // The course path presets core's form with values (set_data), not posted fields.
            return $value;
        }
        switch ($item['kind']) {
            case 'date':
                return self::date_fields((int) $value);
            case 'duration':
                return self::duration_fields((int) $value);
            case 'number':
            case 'percent':
                return is_float($value) ? format_float($value, -1, false, true) : (string) $value;
        }
        return $value;
    }

    /**
     * Replace "{answer:key}" placeholders in a value set by a choice.
     *
     * @param mixed $value the value
     * @param array $answers the answers
     * @return mixed
     */
    protected function substitute($value, array $answers) {
        if (is_array($value)) {
            return array_map(fn($v) => $this->substitute($v, $answers), $value);
        }
        if (is_string($value) && preg_match('/^\{answer:([a-z0-9_]+)\}$/', $value, $m)) {
            $answer = $answers[$m[1]] ?? '';
            return is_float($answer) ? format_float($answer, -1, false, true) : (is_scalar($answer) ? (string) $answer : '');
        }
        return $value;
    }

    /**
     * Set a field by its form name ("name", "grade_forum[modgrade_point]").
     *
     * @param array $fields the fields, changed in place
     * @param string $name the form field name
     * @param mixed $value the value
     */
    public static function set_field(array &$fields, string $name, $value): void {
        preg_match_all('/[^\[\]]+|\[\]/', $name, $m);
        $parts = $m[0];
        $ref = &$fields;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $ref[$part] = $value;
                break;
            }
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
    }

    /**
     * Which question sets each field, for placing the target form's errors.
     *
     * @return array field name (top level) => question key
     */
    public function field_owners(): array {
        $owners = [];
        foreach ($this->questions() as $key => $item) {
            $names = [];
            foreach ($item['choices'] ?? [] as $choice) {
                $names = array_merge($names, array_keys($choice['sets'] ?? []));
            }
            $mappings = isset($item['sets']) ? (array_is_list($item['sets']) ? $item['sets'] : [$item['sets']]) : [];
            foreach ($mappings as $map) {
                if (isset($map['field'])) {
                    $names[] = $map['field'];
                }
            }
            foreach ($names as $name) {
                $top = preg_replace('/\[.*$/', '', $name);
                $owners[$top] ??= $key;
            }
        }
        return $owners;
    }

    /**
     * The form element that shows a question's error.
     *
     * @param string $key the question key
     * @return string
     */
    public function element_for(string $key): string {
        $item = $this->questions()[$key] ?? null;
        if ($item && in_array($item['kind'], ['cards', 'choice'], true)) {
            return $key . 'group';
        }
        return $key;
    }

    /**
     * Place the target form's errors on the questions that set those fields.
     *
     * @param array $errors target field => message
     * @return array form element => message ("wizarderrors" for the rest)
     */
    public function place_errors(array $errors): array {
        $owners = $this->field_owners();
        $placed = [];
        $general = [];
        foreach ($errors as $field => $message) {
            $owner = null;
            foreach ($owners as $name => $key) {
                if ($field === $name || str_starts_with((string) $field, $name)) {
                    $owner = $key;
                    break;
                }
            }
            if ($owner === null) {
                $general[] = $message;
                continue;
            }
            $element = $this->element_for($owner);
            $placed[$element] = isset($placed[$element]) ? $placed[$element] . ' ' . $message : $message;
        }
        if ($general) {
            $placed['wizarderrors'] = implode(' ', $general);
        }
        return $placed;
    }

    /**
     * What the wizard would do, for "Try it".
     *
     * @param array $answers question key => answer
     * @return string[] lines
     */
    public function describe(array $answers): array {
        $lines = [];
        $walk = function ($value, string $name) use (&$walk, &$lines) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, $name === '' ? (string) $k : "{$name}[$k]");
                }
                return;
            }
            $lines[] = $name . ' = ' . (is_bool($value) ? (int) $value : (string) $value);
        };
        $walk($this->fields($answers), '');
        foreach ($this->actions($answers) as [$class, $config]) {
            $line = $class::describe($config, $answers);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    /**
     * The presets of every choice that has one, for the browser.
     *
     * @return array question key => [choice value => [question key => value]]
     */
    public function presets(): array {
        $out = [];
        foreach ($this->questions() as $key => $item) {
            foreach ($item['choices'] ?? [] as $choice) {
                if (!empty($choice['preset'])) {
                    $out[$key][(string) $choice['value']] = $choice['preset'];
                }
            }
        }
        return $out;
    }

    /**
     * A date as a date_time_selector posts it, in the user's timezone.
     *
     * @param int $timestamp the time, or 0 for none
     * @return array
     */
    public static function date_fields(int $timestamp): array {
        if (!$timestamp) {
            return ['enabled' => 0];
        }
        $date = usergetdate($timestamp);
        return ['enabled' => 1, 'day' => $date['mday'], 'month' => $date['mon'], 'year' => $date['year'],
            'hour' => $date['hours'], 'minute' => $date['minutes']];
    }

    /**
     * A duration as an optional duration element posts it.
     *
     * @param int $seconds the duration, or 0 for none
     * @return array
     */
    public static function duration_fields(int $seconds): array {
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
     * The first file in one of the current user's draft areas.
     *
     * @param int $draftitemid the draft area
     * @param bool $imageonly only a valid image
     * @return \stored_file|null
     */
    public static function first_draft_file(int $draftitemid, bool $imageonly = false): ?\stored_file {
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
            if (!$imageonly || $file->is_valid_image()) {
                return $file;
            }
        }
        return null;
    }
}
