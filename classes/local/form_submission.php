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

namespace tool_wizards\local;

/**
 * Submits a core Moodle form as a teacher would who changed only a few fields.
 *
 * The form is rendered, and every control's value is read the way a browser collects
 * them for submission: checked boxes only, a menu's selected or first choice, and no
 * control that the form's own disabledIf() rules disable (as lib/form/form.js does in
 * the browser). The given answers then replace the matching values, and the result is
 * posted back to the same form, whose get_data() returns exactly what the standard page
 * would receive, after the form's own validation.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_submission {
    /**
     * What a browser would submit for a form, untouched or after someone gave these answers.
     *
     * The answers are applied to the controls first and the form's disabledIf() and hideIf()
     * rules are judged afterwards, as the browser judges them after each change. So switching
     * something on sends the fields it enables (with their defaults), and a field the answers
     * disable or hide is not sent, even when it was answered.
     *
     * @param \moodleform $form the form
     * @param array $answers name => value, nested like $_POST
     * @return array name => value, nested like $_POST
     */
    public static function browser_values(\moodleform $form, array $answers = []): array {
        $html = $form->render();
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);

        // Every control, as the browser sees it.
        $controls = [];
        foreach ($xpath->query('//input[@name] | //select[@name] | //textarea[@name]') as $el) {
            if ($el->hasAttribute('disabled')) {
                continue;
            }
            $control = ['name' => $el->getAttribute('name'), 'tag' => $el->nodeName, 'type' => '',
                'checked' => false, 'values' => [], 'multiple' => false, 'class' => $el->getAttribute('class')];
            if ($el->nodeName === 'input') {
                $control['type'] = strtolower($el->getAttribute('type'));
                if (in_array($control['type'], ['submit', 'button', 'reset', 'image', 'file'], true)) {
                    continue;
                }
                $control['checked'] = $el->hasAttribute('checked');
                $default = $control['type'] === 'checkbox' ? 'on' : '';
                $control['values'] = [$el->hasAttribute('value') ? $el->getAttribute('value') : $default];
            } else if ($el->nodeName === 'textarea') {
                $control['values'] = [$el->textContent];
            } else {
                $control['multiple'] = $el->hasAttribute('multiple');
                $options = $xpath->query('.//option', $el);
                foreach ($options as $option) {
                    if ($option->hasAttribute('selected')) {
                        $control['values'][] = $option->getAttribute('value');
                    }
                }
                if (!$control['values'] && $options->length && !$control['multiple']) {
                    $control['values'][] = $options->item(0)->getAttribute('value');
                }
            }
            $controls[] = $control;
        }
        $extra = self::apply_answers($controls, self::flatten($answers));

        // The controls the form's own JavaScript would disable, which a browser does not submit.
        $mform = self::quickform($form);
        [, $dependencies] = $mform->getLockOptionObject();
        $locked = [];
        foreach ($dependencies as $dependenton => $conditions) {
            $elements = array_values(array_filter(
                $controls,
                fn($c) => $c['name'] === $dependenton || $c['name'] === $dependenton . '[]'
            ));
            foreach ($conditions as $condition => $values) {
                foreach ($values as $value => $types) {
                    if (!self::locks($elements, $controls, (string) $condition, (string) $value)) {
                        continue;
                    }
                    // The checkDependencies() of lib/form/form.js locks the dependents of hideIf()
                    // rules as well as those of disabledIf() rules, so a hidden field is not submitted either.
                    foreach ([\MoodleQuickForm::DEP_DISABLE, \MoodleQuickForm::DEP_HIDE] as $kind) {
                        foreach ($types[$kind] ?? [] as $name) {
                            $locked[$name] = true;
                            $locked[$name . '[]'] = true;
                        }
                    }
                }
            }
        }

        $pairs = [];
        foreach ($controls as $control) {
            if (isset($locked[$control['name']]) || self::group_locked($control['name'], $locked)) {
                continue;
            }
            if (in_array($control['type'], ['checkbox', 'radio'], true) && !$control['checked']) {
                continue;
            }
            foreach ($control['values'] as $value) {
                $pairs[] = [$control['name'], $value];
            }
        }
        // Answers for names the form does not render go as they are, unless a rule locks them.
        foreach ($extra as [$name, $value]) {
            if (!isset($locked[$name]) && !self::group_locked($name, $locked)) {
                $pairs[] = [$name, $value];
            }
        }
        $query = implode('&', array_map(fn($p) => rawurlencode($p[0]) . '=' . rawurlencode($p[1]), $pairs));
        parse_str($query, $values);
        return $values;
    }

    /**
     * The values a form offers for each choice control: select options, radio values,
     * checkbox values. Disabled controls are left out.
     *
     * The form is rendered only to read these, so its file pickers and editors are taken out first:
     * rendering one sends the file picker's templates, which Moodle sends only once a request, and
     * the wizard's own file questions in the same request would then open no file picker. The form
     * must be one built for this call alone.
     *
     * @param \moodleform $form the form
     * @return array control name (without "[]") => string[]
     */
    public static function offered_values(\moodleform $form): array {
        $quickform = self::quickform($form);
        foreach ($quickform->_elements as $element) {
            if (in_array($element->getType(), ['filemanager', 'filepicker', 'editor'], true)) {
                $quickform->removeElement($element->getName());
            }
        }
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $form->render());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $out = [];
        foreach ($xpath->query('//select[@name]') as $select) {
            $name = preg_replace('/\[\]$/', '', $select->getAttribute('name'));
            foreach ($xpath->query('.//option', $select) as $option) {
                $out[$name][] = $option->getAttribute('value');
            }
        }
        foreach ($xpath->query('//input[@name][@type="radio" or @type="checkbox"]') as $input) {
            if (!$input->hasAttribute('disabled')) {
                $out[$input->getAttribute('name')][] = $input->hasAttribute('value') ? $input->getAttribute('value') : 'on';
            }
        }
        return array_map('array_values', array_map('array_unique', $out));
    }

    /**
     * Answers as the flat name => value pairs a browser posts ("intro[text]", "tags[]").
     *
     * @param array $answers name => value, nested like $_POST
     * @param string $prefix the enclosing name, if any
     * @return array of [name, value]
     */
    protected static function flatten(array $answers, string $prefix = ''): array {
        $pairs = [];
        foreach ($answers as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $pairs = array_merge($pairs, self::flatten($value, $name));
            } else {
                $pairs[] = [$name, (string) (is_bool($value) ? (int) $value : $value)];
            }
        }
        return $pairs;
    }

    /**
     * Set the controls to the answers, as someone filling the form in would.
     *
     * @param array $controls every control, changed in place
     * @param array $pairs answers as [name, value] pairs
     * @return array the pairs no control took
     */
    protected static function apply_answers(array &$controls, array $pairs): array {
        // Group the answers by the control name that takes them: "tags[0]", "tags[1]" go to "tags[]"
        // when the form renders a multiple select of that name.
        $names = array_column($controls, 'name');
        $byname = [];
        foreach ($pairs as [$name, $value]) {
            $target = $name;
            $indexed = !in_array($name, $names, true) && preg_match('/^(.*)\[\d+\]$/', $name, $m);
            if ($indexed && in_array($m[1] . '[]', $names, true)) {
                $target = $m[1] . '[]';
            }
            $byname[$target][] = $value;
        }

        $taken = [];
        foreach ($controls as $i => $control) {
            $name = $control['name'];
            if (!array_key_exists($name, $byname)) {
                continue;
            }
            $values = $byname[$name];
            $taken[$name] = true;
            switch ($control['type']) {
                case 'checkbox':
                    $on = $values[0];
                    $controls[$i]['checked'] = $on === $control['values'][0]
                        || ($control['values'][0] === 'on' && !empty($on));
                    break;
                case 'radio':
                    $controls[$i]['checked'] = in_array($control['values'][0], $values, true);
                    break;
                case 'hidden':
                    // The hidden half of an advcheckbox always posts its "off" value; the checkbox decides.
                    if (!self::has_checkbox_twin($name, $controls)) {
                        $controls[$i]['values'] = [$values[0]];
                    }
                    break;
                default:
                    $controls[$i]['values'] = $control['tag'] === 'select' && $control['multiple'] ? $values : [$values[0]];
            }
        }

        $extra = [];
        foreach ($pairs as [$name, $value]) {
            $target = $name;
            if (preg_match('/^(.*)\[\d+\]$/', $name, $m) && isset($taken[$m[1] . '[]'])) {
                $target = $m[1] . '[]';
            }
            if (!isset($taken[$target])) {
                $extra[] = [$name, $value];
            }
        }
        return $extra;
    }

    /**
     * Whether a control inside a group ("group[child]") belongs to a disabled group.
     *
     * @param string $name the control's name
     * @param array $locked disabled names
     * @return bool
     */
    protected static function group_locked(string $name, array $locked): bool {
        $pos = strpos($name, '[');
        return $pos !== false && isset($locked[substr($name, 0, $pos)]);
    }

    /**
     * Evaluate one disabledIf() condition the way lib/form/form.js does.
     *
     * @param array $elements the controls named like the depended-on element
     * @param array $controls every control (for the hidden half of an advcheckbox)
     * @param string $condition notchecked, checked, noitemselected, eq, in, hide, or anything else (not equal)
     * @param string $value the condition's value
     * @return bool whether the dependents are disabled
     */
    protected static function locks(array $elements, array $controls, string $condition, string $value): bool {
        $lock = false;
        $hiddenval = false;
        foreach ($elements as $el) {
            $isadvhidden = $el['type'] === 'hidden' && self::has_checkbox_twin($el['name'], $controls);
            $current = $el['values'][0] ?? '';
            switch ($condition) {
                case 'notchecked':
                case 'checked':
                    if ($isadvhidden || ($el['type'] === 'radio' && $current !== $value)) {
                        break;
                    }
                    $lock = $lock || ($condition === 'checked' ? $el['checked'] : !$el['checked']);
                    break;
                case 'noitemselected':
                    $lock = $lock || ($el['tag'] === 'select' && !$el['values']);
                    break;
                case 'hide':
                    break;
                default:
                    $in = $condition === 'in' ? explode('|', $value) : null;
                    $matches = fn($v) => $in !== null ? in_array($v, $in, true) : $v == $value;
                    $equal = $condition === 'eq' || $condition === 'in';
                    if ($el['type'] === 'radio' && !$el['checked']) {
                        break;
                    }
                    if ($isadvhidden) {
                        $hiddenval = $equal ? $matches($current) : !$matches($current);
                        break;
                    }
                    if ($el['type'] === 'checkbox' && !$el['checked']) {
                        $lock = $lock || $hiddenval;
                        break;
                    }
                    if ($el['tag'] === 'select' && $el['multiple']) {
                        $wanted = explode('|', $value);
                        $selected = $el['values'];
                        if ($condition === 'in') {
                            // As in form.js _dependencyIn(): nothing selected counts as '', and the selection must be a subset.
                            $selected = $selected ?: [''];
                            $lock = !array_diff($selected, $wanted);
                            break;
                        }
                        $same = count($selected) === count($wanted) && !array_diff($selected, $wanted);
                        if ($wanted === ['']) {
                            $same = !$selected;
                        }
                        $lock = $equal ? $same : !$same;
                        break;
                    }
                    $lock = $lock || ($equal ? $matches($current) : !$matches($current));
            }
        }
        return $lock;
    }

    /**
     * Whether a hidden input is the unchecked half of an advcheckbox.
     *
     * @param string $name the input name
     * @param array $controls every control
     * @return bool
     */
    protected static function has_checkbox_twin(string $name, array $controls): bool {
        foreach ($controls as $c) {
            if ($c['name'] === $name && $c['type'] === 'checkbox') {
                return true;
            }
        }
        return false;
    }

    /**
     * The QuickForm inside a Moodle form.
     *
     * @param \moodleform $form the form
     * @return \MoodleQuickForm
     */
    protected static function quickform(\moodleform $form): \MoodleQuickForm {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }

    /**
     * Submit values to a form and read them back through its own get_data().
     *
     * The request's own $_POST is kept aside and put back afterwards.
     *
     * @param callable $factory returns a new instance of the form, built as its page builds it
     * @param array $values name => value, nested like $_POST
     * @return array [\moodleform $form, \stdClass|null $data, array $errors]
     */
    public static function submit(callable $factory, array $values): array {
        $savedpost = $_POST;
        try {
            $form = $factory();
            $form::mock_submit($values);
            $form = $factory();
            $data = $form->get_data();
            $errors = $data ? [] : self::quickform($form)->_errors;
        } finally {
            $_POST = $savedpost;
        }
        return [$form, $data ?: null, $errors];
    }
}
