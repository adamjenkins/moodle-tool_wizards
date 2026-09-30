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
     * What a browser would submit for a form nobody touched.
     *
     * @param \moodleform $form the form
     * @return array name => value, nested like $_POST
     */
    public static function browser_values(\moodleform $form): array {
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
        $query = implode('&', array_map(fn($p) => rawurlencode($p[0]) . '=' . rawurlencode($p[1]), $pairs));
        parse_str($query, $values);
        return $values;
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
