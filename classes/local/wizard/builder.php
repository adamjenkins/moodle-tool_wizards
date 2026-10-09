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

/**
 * Turns the wizard builder's answers into a definition.
 *
 * Text the admin types is stored inline in their current language, so it can be translated
 * in the editor afterwards.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class builder {
    /**
     * Text in the current language.
     *
     * @param string $value the text
     * @return array
     */
    protected static function t(string $value): array {
        return [current_language() => trim($value)];
    }

    /**
     * A question key from a form field name.
     *
     * @param string $field the field
     * @param string[] $used keys already used
     * @return string
     */
    public static function key_for(string $field, array $used): string {
        $key = trim(preg_replace('/[^a-z0-9_]+/', '_', strtolower($field)), '_');
        if ($key === '' || !preg_match('/^[a-z]/', $key)) {
            $key = 'q_' . $key;
        }
        $key = substr($key, 0, 50);
        $candidate = $key;
        for (
            $i = 2; in_array($candidate, $used, true)
                || in_array($candidate, ['courseid', 'wizard', 'section', 'preview', 'sesskey', 'id', 'startcategory',
                    'fullsettings', 'wizarderrors', 'purpose'], true); $i++
        ) {
            $candidate = $key . '_' . $i;
        }
        return $candidate;
    }

    /**
     * The questions from steps 2 and 3.
     *
     * @param string $target "course" or a module name
     * @param array $settings the chosen settings (introspector::settings() entries)
     * @param array $answers step 3's q[...] values
     * @return array list of ['question' => array, 'screen' => string]
     */
    public static function questions(string $target, array $settings, array $answers): array {
        $out = [];
        $used = [];
        foreach ($settings as $i => $setting) {
            $a = (array) ($answers[$i] ?? []);
            $kind = (string) ($a['kind'] ?? introspector::kinds_for($setting)[0]);
            $key = self::key_for($setting['field'], $used);
            $used[] = $key;
            $label = trim((string) ($a['label'] ?? '')) ?: $setting['label'];
            $question = ['key' => $key, 'kind' => $kind, 'label' => self::t($label)];
            if (trim((string) ($a['help'] ?? '')) !== '') {
                $question['help'] = self::t((string) $a['help']);
            }
            if (!empty($a['required'])) {
                $question['required'] = true;
            }
            if (in_array($kind, validator::CHOICE_KINDS, true)) {
                $choices = [];
                if ($setting['type'] === 'yesno') {
                    $choices = [['value' => 1, 'title' => self::t(get_string('yes'))],
                        ['value' => 0, 'title' => self::t(get_string('no'))]];
                } else {
                    $c = 0;
                    foreach ($setting['choices'] as $value => $optionlabel) {
                        $ca = (array) ($a['choices'][$c++] ?? []);
                        if (isset($ca['offer']) && empty($ca['offer'])) {
                            continue;
                        }
                        $choice = ['value' => is_numeric($value) && (string) (int) $value === (string) $value ? (int) $value
                            : (string) $value, 'title' => self::t(trim((string) ($ca['title'] ?? '')) ?: $optionlabel)];
                        if (trim((string) ($ca['desc'] ?? '')) !== '') {
                            $choice['desc'] = self::t((string) $ca['desc']);
                        }
                        $choices[] = $choice;
                    }
                }
                if (!$choices) {
                    continue;
                }
                $question['choices'] = $choices;
            }
            $sets = ['field' => $setting['field']];
            if (in_array($setting['field'], ['introeditor', 'summary_editor'], true) && $kind === 'textarea') {
                $sets['as'] = 'editor';
            } else if ($setting['type'] === 'yesno') {
                $sets['as'] = 'int';
            } else if ($kind === 'file') {
                $sets['as'] = 'int';
            }
            $question['sets'] = $sets;
            $out[] = ['question' => $question,
                'screen' => trim((string) ($a['screen'] ?? '')) ?: ($setting['header'] ?: get_string('general'))];
        }
        return $out;
    }

    /**
     * The definition.
     *
     * @param array $state the builder's state: target, title, description, shared, questions, purposequestion, purposes
     * @return array
     */
    public static function definition(array $state): array {
        $target = $state['target'];
        $doc = [
            'format' => validator::FORMAT,
            'key' => repository::free_key(self::key_for(($target === 'course' ? 'course_' : '') . $state['title'], [])),
            'target' => $target === 'course' ? ['type' => 'course'] : ['type' => 'module', 'modname' => $target],
            'title' => self::t($state['title']),
        ];
        if (trim((string) ($state['description'] ?? '')) !== '') {
            $doc['description'] = self::t($state['description']);
        }
        $screens = [];
        $order = [];
        foreach ($state['questions'] as $entry) {
            $name = $entry['screen'];
            if (!isset($screens[$name])) {
                $order[] = $name;
                $screens[$name] = [];
            }
            $screens[$name][] = $entry['question'];
        }

        // Purposes become picture cards on the first screen.
        $purposes = [];
        foreach ($state['purposes'] ?? [] as $i => $purpose) {
            if (trim((string) ($purpose['title'] ?? '')) === '') {
                continue;
            }
            $choice = ['value' => 'p' . ($i + 1), 'title' => self::t($purpose['title'])];
            if (trim((string) ($purpose['desc'] ?? '')) !== '') {
                $choice['desc'] = self::t($purpose['desc']);
            }
            if (!empty($purpose['picture'])) {
                $choice['picture'] = $purpose['picture'];
            }
            $preset = array_filter((array) ($purpose['preset'] ?? []), fn($v) => $v !== '' && $v !== null);
            if ($preset) {
                $choice['preset'] = array_map(
                    fn($v) => is_numeric($v) && (string) (int) $v === (string) $v ? (int) $v : $v,
                    $preset
                );
            }
            $purposes[] = $choice;
        }
        if ($purposes && $order) {
            $question = trim((string) ($state['purposequestion'] ?? '')) ?: get_string(
                'build_purposequestion_default',
                'tool_wizards'
            );
            $screens[$order[0]][] = ['key' => 'purpose', 'kind' => 'cards', 'label' => self::t($question), 'required' => true,
                'choices' => $purposes];
        }

        $used = [];
        $doc['screens'] = [];
        foreach ($order as $i => $name) {
            $key = self::key_for($i === 0 ? 'basics' : $name, $used);
            $used[] = $key;
            $doc['screens'][] = ['key' => $key, 'title' => self::t($name), 'items' => $screens[$name]];
        }
        if ($target !== 'course' && !empty($state['shared'])) {
            $doc['screens'][] = ['use' => 'shared:visibility'];
            $doc['screens'][] = ['use' => 'shared:groups'];
            $doc['screens'][] = ['use' => 'shared:completion'];
        }
        if ($target === 'course') {
            $doc['screens'][] = ['key' => 'review', 'title' => ['string' => 'step_review'],
                'question' => ['string' => 'step_review_title'], 'items' => [['kind' => 'review']]];
        }
        return $doc;
    }
}
