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
 * Reads a target's own settings form, for the wizard builder: which settings it has, how
 * the form labels and groups them, and the choices it offers.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class introspector {
    /** @var string[] Fields a wizard should not offer: handled elsewhere or not settable from a wizard. */
    const SKIP = ['course', 'coursemodule', 'section', 'module', 'modulename', 'instance', 'add', 'update', 'return', 'sr',
        'beforemod', 'showonly', 'competencies', 'competency_rule', 'tags', 'availabilityconditionsjson', 'mform_isexpanded',
        'completionunlocked', 'id', 'returnto', 'returnurl', 'category', 'cmidnumber', 'downloadcontent', 'lang', 'theme',
        'idnumber', 'enddate', 'relativedatesmode', 'cacherev'];

    /**
     * The modules a wizard can add: installed, enabled, with a settings form.
     *
     * @return array module name => display name
     */
    public static function modules(): array {
        global $CFG, $DB;
        $out = [];
        foreach ($DB->get_records('modules', ['visible' => 1], 'name', 'id, name') as $module) {
            if (is_readable($CFG->dirroot . '/mod/' . $module->name . '/mod_form.php')) {
                $out[$module->name] = get_string('modulename', 'mod_' . $module->name);
            }
        }
        \core_collator::asort($out);
        return $out;
    }

    /**
     * A course to read activity forms in: any course but the site.
     *
     * @return \stdClass|null
     */
    public static function sample_course(): ?\stdClass {
        global $DB;
        $id = $DB->get_field_sql('SELECT MIN(id) FROM {course} WHERE id <> ?', [SITEID]);
        return $id ? get_course($id) : null;
    }

    /**
     * The settings of a target's form.
     *
     * @param string $target "course" or a module name
     * @return array list of ['field', 'label', 'type', 'choices' (value => label), 'header']
     */
    public static function settings(string $target): array {
        global $PAGE, $COURSE, $OUTPUT;
        // Building an activity form sets the page's course and context, which a page already
        // set up for output must not change: build it on a page of its own. The renderer may
        // attach itself to that page meanwhile, so it is put back too.
        $savedpage = $PAGE;
        $savedcourse = $COURSE;
        $savedoutput = $OUTPUT;
        $PAGE = new \moodle_page();
        try {
            if ($target === 'course') {
                $form = course_creator::course_form((int) \core_course_category::get_default()->id);
            } else {
                $course = self::sample_course();
                if (!$course) {
                    return [];
                }
                $form = module_creator::form($course, $target);
            }
        } finally {
            $PAGE = $savedpage;
            $COURSE = $savedcourse;
            $OUTPUT = $savedoutput;
        }
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        $out = [];
        $header = '';
        foreach ($mform->_elements as $element) {
            $type = $element->getType();
            $name = (string) $element->getName();
            if ($type === 'header') {
                $header = trim(strip_tags((string) $element->_text));
                continue;
            }
            if (
                $name === '' || in_array($name, self::SKIP, true) || str_starts_with($name, 'completion')
                    || str_starts_with($name, 'grade') || $element->isFrozen()
            ) {
                continue;
            }
            $label = trim(strip_tags((string) $element->getLabel()));
            $setting = ['field' => $name, 'label' => $label !== '' ? $label : $name, 'header' => $header, 'choices' => [],
                'required' => $mform->isElementRequired($name)];
            switch ($type) {
                case 'select':
                case 'selectyesno':
                case 'modvisible':
                    if ($element->getMultiple()) {
                        continue 2;
                    }
                    foreach ($element->_options as $option) {
                        $setting['choices'][(string) $option['attr']['value']] = trim(strip_tags((string) $option['text']));
                    }
                    $setting['type'] = 'select';
                    break;
                case 'advcheckbox':
                case 'checkbox':
                    $setting['type'] = 'yesno';
                    if ($label === '') {
                        $setting['label'] = trim(strip_tags((string) $element->_text)) ?: $name;
                    }
                    break;
                case 'text':
                    $paramtype = $mform->_types[$name] ?? PARAM_RAW;
                    $setting['type'] = in_array($paramtype, [PARAM_INT, PARAM_FLOAT], true) ? 'number' : 'text';
                    break;
                case 'textarea':
                    $setting['type'] = 'textarea';
                    break;
                case 'editor':
                    $setting['type'] = 'editor';
                    break;
                case 'date_time_selector':
                case 'date_selector':
                    $setting['type'] = 'date';
                    break;
                case 'duration':
                    $setting['type'] = 'duration';
                    break;
                case 'filemanager':
                    $setting['type'] = 'file';
                    break;
                case 'group':
                    $radios = array_filter($element->getElements(), fn($e) => $e->getType() === 'radio');
                    if (!$radios || count($radios) !== count($element->getElements())) {
                        continue 2;
                    }
                    foreach ($radios as $radio) {
                        $setting['choices'][(string) $radio->getValue()] = trim(strip_tags((string) $radio->_text));
                    }
                    $setting['field'] = $radios ? reset($radios)->getName() : $name;
                    $setting['type'] = 'select';
                    break;
                default:
                    continue 2;
            }
            $out[$setting['field']] = $setting;
        }
        return array_values($out);
    }

    /**
     * The question kinds suited to a setting type, the first being the suggestion.
     *
     * @param array $setting the setting
     * @return string[]
     */
    public static function kinds_for(array $setting): array {
        switch ($setting['type']) {
            case 'select':
                return count($setting['choices']) <= 6 ? ['choice', 'cards', 'select'] : ['select', 'choice', 'cards'];
            case 'yesno':
                return ['yesno', 'choice'];
            case 'number':
                return ['number', 'text'];
            case 'text':
                return ['text', 'textarea'];
            case 'textarea':
                return ['textarea', 'text'];
            case 'editor':
                return $setting['field'] === 'introeditor' || $setting['field'] === 'summary_editor'
                    ? ['textarea', 'editor'] : ['editor', 'textarea'];
            default:
                return [$setting['type']];
        }
    }
}
