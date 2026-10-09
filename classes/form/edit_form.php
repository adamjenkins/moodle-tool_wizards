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

use tool_wizards\local\wizard\repository;
use tool_wizards\local\wizard\text;
use tool_wizards\local\wizard\texts;

/**
 * The structured wizard editor: status, every text in every installed language, the order of
 * the screens, and pictures. Changing the questions themselves is done with the builder,
 * "Improve with AI", or the JSON editor.
 *
 * Custom data: record (the wizard), doc (its definition).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_form extends \moodleform {
    /** @var string The "all languages" key for plain texts. */
    const PLAIN = 'all';

    /**
     * The installed languages.
     *
     * @return array code => name
     */
    public static function languages(): array {
        return get_string_manager()->get_list_of_translations();
    }

    /**
     * The fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $record = $this->_customdata['record'];
        $doc = $this->_customdata['doc'];
        $languages = self::languages();

        $mform->addElement('hidden', 'id', $record->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'generalhdr', get_string('edit_general', 'tool_wizards'));
        $mform->addElement('select', 'status', get_string('status', 'tool_wizards'), [
            repository::STATUS_ENABLED => get_string('status_enabled', 'tool_wizards'),
            repository::STATUS_DISABLED => get_string('status_disabled', 'tool_wizards'),
            repository::STATUS_DRAFT => get_string('status_draft', 'tool_wizards'),
        ]);
        $mform->addElement(
            'filemanager',
            'pictures',
            get_string('edit_pictures', 'tool_wizards'),
            null,
            ['subdirs' => 0, 'maxfiles' => 50, 'maxbytes' => 2097152, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp',
            '.svg',
            '.gif']]
        );
        $mform->addHelpButton('pictures', 'edit_pictures', 'tool_wizards');

        $pictures = $this->picture_options();
        $mform->addElement('select', 'pic_doc', get_string('edit_listpicture', 'tool_wizards'), $pictures);

        $current = null;
        foreach (texts::all($doc) as $i => $entry) {
            $screen = $entry['path'][0] === 'screens' ? $entry['path'][1] : null;
            if ($screen !== $current && $screen !== null) {
                $current = $screen;
                $this->screen_header($doc['screens'][$screen], $screen);
            }
            $this->text_fields($i, $entry, $languages);
        }
        // Screens without texts of their own (shared screens) still get order and removal.
        foreach ($doc['screens'] as $s => $screen) {
            if (!$mform->elementExists("pos[$s]")) {
                $this->screen_header($screen, $s);
            }
        }
        // Card pictures.
        $mform->addElement('header', 'pictureshdr', get_string('edit_cardpictures', 'tool_wizards'));
        foreach ($this->card_paths($doc) as $name => [$path, $label]) {
            $mform->addElement('select', $name, $label, $pictures);
        }
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * A screen's header, position and removal.
     *
     * @param array $screen the screen
     * @param int $index its index
     */
    protected function screen_header(array $screen, int $index): void {
        $mform = $this->_form;
        $title = isset($screen['title']) ? text::get($screen['title']) : ($screen['use'] ?? '');
        $mform->addElement('header', "screenhdr$index", get_string('edit_screen', 'tool_wizards', s($title)));
        $mform->setExpanded("screenhdr$index", false);
        $mform->addElement('text', "pos[$index]", get_string('edit_position', 'tool_wizards'), ['size' => 3]);
        $mform->setType("pos[$index]", PARAM_INT);
        if ($index > 0) {
            $mform->addElement('advcheckbox', "remove[$index]", '', get_string('edit_removescreen', 'tool_wizards'));
        }
    }

    /**
     * One text's fields: one per installed language, or one for a plain text.
     *
     * @param int $i the text's index
     * @param array $entry the text entry
     * @param array $languages installed languages
     */
    protected function text_fields(int $i, array $entry, array $languages): void {
        $mform = $this->_form;
        $label = s($entry['where']);
        if (is_string($entry['text'])) {
            $mform->addElement('text', "txt[$i][" . self::PLAIN . "]", $label . ' (' . get_string(
                'edit_alllanguages',
                'tool_wizards'
            ) . ')', ['size' => 60]);
            $mform->setType("txt[$i][" . self::PLAIN . "]", PARAM_TEXT);
            return;
        }
        foreach ($languages as $lang => $name) {
            $attributes = ['size' => 60];
            if (isset($entry['text']['string'])) {
                $attributes['placeholder'] = text::get(['string' => $entry['text']['string']]
                    + (isset($entry['text']['component']) ? ['component' => $entry['text']['component']] : []), $lang);
            }
            $attributes['title'] = $name;
            $mform->addElement('text', "txt[$i][$lang]", $label . ' [' . $lang . ']', $attributes);
            $mform->setType("txt[$i][$lang]", PARAM_TEXT);
        }
    }

    /**
     * The picture choices: none, the shipped pictures, and this wizard's uploads.
     *
     * @return array reference => label
     */
    protected function picture_options(): array {
        global $CFG;
        $options = ['' => get_string('none')];
        foreach (glob($CFG->dirroot . '/admin/tool/wizards/pix/purpose/*.svg') ?: [] as $file) {
            $name = basename($file, '.svg');
            $options['pix:purpose/' . $name] = get_string('edit_shippedpicture', 'tool_wizards', $name);
        }
        $files = get_file_storage()->get_area_files(
            \core\context\system::instance()->id,
            'tool_wizards',
            'picture',
            $this->_customdata['record']->id,
            'filename',
            false
        );
        foreach ($files as $file) {
            $options['file:' . $file->get_filename()] = get_string('edit_uploadedpicture', 'tool_wizards', $file->get_filename());
        }
        return $options;
    }

    /**
     * The choices of card questions, which can have pictures.
     *
     * @param array $doc the definition
     * @return array field name => [path, label]
     */
    public static function card_paths(array $doc): array {
        $out = [];
        foreach ($doc['screens'] as $s => $screen) {
            foreach ($screen['items'] ?? [] as $j => $item) {
                if (($item['kind'] ?? '') !== 'cards') {
                    continue;
                }
                foreach ($item['choices'] ?? [] as $c => $choice) {
                    $out["pic_{$s}_{$j}_{$c}"] = [['screens', $s, 'items', $j, 'choices', $c, 'picture'],
                        s(($item['key'] ?? '') . ' › ' . text::get($choice['title'] ?? ''))];
                }
            }
        }
        return $out;
    }

    /**
     * The form's values for a wizard.
     *
     * @param \stdClass $record the wizard
     * @param array $doc its definition
     * @return array
     */
    public static function values(\stdClass $record, array $doc): array {
        $values = ['id' => $record->id, 'status' => (int) $record->status, 'pic_doc' => $doc['picture'] ?? ''];
        foreach (texts::all($doc) as $i => $entry) {
            if (is_string($entry['text'])) {
                $values["txt[$i][" . self::PLAIN . "]"] = $entry['text'];
                continue;
            }
            foreach (text::inline($entry['text']) as $lang => $value) {
                $values["txt[$i][$lang]"] = $value;
            }
        }
        foreach ($doc['screens'] as $s => $screen) {
            $values["pos[$s]"] = $s + 1;
        }
        foreach (self::card_paths($doc) as $name => [$path]) {
            $choice = $doc['screens'][$path[1]]['items'][$path[3]]['choices'][$path[5]];
            $values[$name] = $choice['picture'] ?? '';
        }
        return $values;
    }

    /**
     * Apply submitted values to a definition.
     *
     * @param array $doc the definition
     * @param \stdClass $data submitted data
     * @return array the changed definition
     */
    public static function apply(array $doc, \stdClass $data): array {
        $txt = (array) ($data->txt ?? []);
        foreach (texts::all($doc) as $i => $entry) {
            $values = (array) ($txt[$i] ?? []);
            if (is_string($entry['text'])) {
                texts::set($doc, $entry['path'], trim((string) ($values[self::PLAIN] ?? $entry['text'])));
                continue;
            }
            $text = $entry['text'];
            foreach (array_keys(self::languages()) as $lang) {
                $text = text::with_language($text, $lang, trim((string) ($values[$lang] ?? '')));
            }
            texts::set($doc, $entry['path'], $text);
        }
        if (isset($data->pic_doc)) {
            if ($data->pic_doc === '') {
                unset($doc['picture']);
            } else {
                $doc['picture'] = $data->pic_doc;
            }
        }
        foreach (self::card_paths($doc) as $name => [$path]) {
            $value = $data->$name ?? '';
            $choice = &$doc['screens'][$path[1]]['items'][$path[3]]['choices'][$path[5]];
            if ($value === '') {
                unset($choice['picture']);
            } else {
                $choice['picture'] = $value;
            }
            unset($choice);
        }
        // Order and removal: the first screen stays first.
        $pos = (array) ($data->pos ?? []);
        $remove = (array) ($data->remove ?? []);
        $screens = [];
        foreach ($doc['screens'] as $s => $screen) {
            if ($s > 0 && !empty($remove[$s])) {
                continue;
            }
            $screens[] = ['pos' => $s === 0 ? -PHP_INT_MAX : (int) ($pos[$s] ?? $s + 1), 'index' => $s, 'screen' => $screen];
        }
        usort($screens, fn($a, $b) => [$a['pos'], $a['index']] <=> [$b['pos'], $b['index']]);
        $doc['screens'] = array_column($screens, 'screen');
        return $doc;
    }
}
