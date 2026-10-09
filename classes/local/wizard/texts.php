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
 * Every piece of text in a definition, with where it is, for translating and editing.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class texts {
    /** @var string[] Text properties of a question. */
    const ITEM_TEXTS = ['label', 'help', 'text', 'requiredmessage', 'placeholder'];

    /**
     * The texts.
     *
     * @param array $doc the definition
     * @return array list of ['path' => (string|int)[], 'text' => mixed, 'where' => string]
     */
    public static function all(array $doc): array {
        $out = [];
        foreach (['title', 'heading', 'description', 'intro'] as $name) {
            if (isset($doc[$name])) {
                $out[] = ['path' => [$name], 'text' => $doc[$name], 'where' => $name];
            }
        }
        foreach ($doc['screens'] ?? [] as $i => $screen) {
            $skey = $screen['key'] ?? ($screen['use'] ?? $i);
            foreach (['title', 'question'] as $name) {
                if (isset($screen[$name])) {
                    $out[] = ['path' => ['screens', $i, $name], 'text' => $screen[$name], 'where' => "$skey › $name"];
                }
            }
            foreach ($screen['choices'] ?? [] as $c => $choice) {
                $out = array_merge($out, self::choice_texts($choice, ['screens', $i, 'choices', $c], "$skey › {$choice['value']}"));
            }
            foreach ($screen['items'] ?? [] as $j => $item) {
                $ikey = $item['key'] ?? ($item['kind'] ?? $j);
                foreach (self::ITEM_TEXTS as $name) {
                    if (isset($item[$name])) {
                        $out[] = ['path' => ['screens', $i, 'items', $j, $name], 'text' => $item[$name],
                            'where' => "$skey › $ikey › $name"];
                    }
                }
                if (isset($item['extensions']['message'])) {
                    $out[] = ['path' => ['screens', $i, 'items', $j, 'extensions', 'message'],
                        'text' => $item['extensions']['message'], 'where' => "$skey › $ikey › extensions"];
                }
                foreach ($item['choices'] ?? [] as $c => $choice) {
                    $out = array_merge($out, self::choice_texts(
                        $choice,
                        ['screens', $i, 'items', $j, 'choices', $c],
                        "$skey › $ikey › " . ($choice['value'] ?? $c)
                    ));
                }
            }
        }
        return $out;
    }

    /**
     * A choice's texts.
     *
     * @param array $choice the choice
     * @param array $path its path
     * @param string $where a readable place
     * @return array
     */
    protected static function choice_texts(array $choice, array $path, string $where): array {
        $out = [];
        foreach (['title', 'desc'] as $name) {
            if (isset($choice[$name])) {
                $out[] = ['path' => array_merge($path, [$name]), 'text' => $choice[$name], 'where' => "$where › $name"];
            }
        }
        return $out;
    }

    /**
     * The installed languages in which every text of the definition has a value.
     *
     * @param array $doc the definition
     * @return string[] language codes
     */
    public static function complete_languages(array $doc): array {
        $complete = [];
        $all = self::all($doc);
        foreach (array_keys(get_string_manager()->get_list_of_translations()) as $lang) {
            $ok = true;
            foreach ($all as $entry) {
                $text = $entry['text'];
                // A language pack reference counts as complete: the pack translates it (or falls back).
                if (!text::has_language($text, $lang) && !(is_array($text) && isset($text['string']))) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $complete[] = $lang;
            }
        }
        return $complete;
    }

    /**
     * Set a value at a path.
     *
     * @param array $doc the definition, changed in place
     * @param array $path the path
     * @param mixed $value the value
     */
    public static function set(array &$doc, array $path, $value): void {
        $ref = &$doc;
        foreach ($path as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }
}
